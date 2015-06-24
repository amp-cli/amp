<?php
namespace Amp\Command;

// TODO: Clean these
use Amp\ConfigRepository;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigUpgradeCommand extends ContainerAwareCommand {

  /**
   * @var ConfigRepository
   */
  private $config;

  /**
   * @var array
   */
  private static $map = array(
    'mysql_type' => array(
      'name' => 'db_type',
      'values' => array(
        'dsn' => 'mysql_dsn',
        'ram_disk' => 'mysql_ram_disk',
        'osx_ram_disk' => 'mysql_osx_ram_disk'
        )
      ),
    'mysql.dsn' => array(
      'name' => 'db.mysql_dsn',
      'values' => array()
      ),
    'mysql.ram_disk' => array(
      'name' => 'db.mysql_ram_disk',
      'values' => array()
      ),
    'mysql.osx_ram_disk' => array(
      'name' => 'db.mysql_osx_ram_disk',
      'values' => array()
      )
  );

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct(\Amp\Application $app, $name = NULL, ConfigRepository $config = NULL) {
    $this->config = $config;
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('config:upgrade')
      ->setDescription('Upgrade the configuration');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
//  print_r( $this->getContainer() );
//  print_r( $this->config );
    $container = $this->getContainer();
    foreach (self::$map as $from => $mapping) {
      if ($container->hasParameter($from)) {
        $oldValue = $container->getParameter($from);
        echo "set " . $mapping['name'] . ' to ' . $mapping['values'][$oldValue] . "\n";
        $container->setParameter($mapping['name'], $mapping['values'][$oldValue]);
        $container->getParameterBag()->remove($from);
        $this->config->setParameter($mapping['name'], $mapping['values'][$oldValue]);
        $this->config->unsetParameter($from);
      }
    }
    $container->setAlias('db', 'db.' . $container->getParameter('db_type'));
    $this->config->save();
  }

}