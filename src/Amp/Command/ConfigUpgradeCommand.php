<?php
namespace Amp\Command;

// TODO: Clean these
use Amp\ConfigRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

define('LATEST_SCHEMA_VERSION', 2);


class ConfigUpgradeCommand extends ContainerAwareCommand {

  /**
   * Parent has a $app, but it's private.  This class needs it.
   * @var \Amp\Application
   */
  private $app;

  /**
   * @var \Amp\ConfigRepository
   */
  private $config;

  /**
   * @var array
   */
  private static $mapV1ToV2 = array(
    'mysql_type' => array(
      'name' => 'db_type',
      'values' => array(
        'dsn' => 'mysql_dsn',
        'ram_disk' => 'mysql_ram_disk',
        'osx_ram_disk' => 'mysql_osx_ram_disk',
        'mycnf' => 'mysql_mycnf',
      ),
    ),
    'mysql.mycnf' => array(
      'name' => 'db.mysql_mycnf',
      'values' => array(),
    ),
    'mysql.dsn' => array(
      'name' => 'db.mysql_dsn',
      'values' => array(),
    ),
    'mysql.ram_disk' => array(
      'name' => 'db.mysql_ram_disk',
      'values' => array(),
    ),
    'mysql.osx_ram_disk' => array(
      'name' => 'db.mysql_osx_ram_disk',
      'values' => array(),
    ),
  );

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param \Amp\ConfigRepository|null $config
   */
  public function __construct(\Amp\Application $app, $name = NULL, ConfigRepository $config = NULL) {
    $this->config = $config;
    $this->app = $app;
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('config:upgrade')
      ->setDescription('Upgrade the configuration');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $startVer = $this->config->getParameter('version');
    if (empty($startVer)) {
      // Legacy file which predates versioning.
      $startVer = 1;
    }

    if ($startVer === 'new') {
      $this->config->setParameter('version', LATEST_SCHEMA_VERSION);
      $this->config->save();
    }
    elseif ($startVer < LATEST_SCHEMA_VERSION) {
      switch ($startVer) {
        case 1:
          $this->upgradeV1ToV2();
          //case 2:
          // $this->upgradeV2ToV3();
        case 'finished':
          break;

        default:
          throw new RuntimeException("Unrecognized schema start version");
      }
      $this->config->save();
      $this->resetContainer();
    }
    return 0;
  }

  protected function upgradeV1ToV2() {
    $container = $this->getContainer();
    foreach (self::$mapV1ToV2 as $from => $mapping) {
      if ($container->hasParameter($from)) {
        $oldValue = $container->getParameter($from);
        $container->setParameter($mapping['name'], $mapping['values'][$oldValue]);
        $container->getParameterBag()->remove($from);
        $this->config->setParameter($mapping['name'], $mapping['values'][$oldValue]);
        $this->config->unsetParameter($from);
      }
    }
    $container->setAlias('db', 'db.' . $container->getParameter('db_type'));
    $this->config->setParameter('version', LATEST_SCHEMA_VERSION);
  }

  protected function resetContainer() {
    $this->app->loadContainer();
  }

}
