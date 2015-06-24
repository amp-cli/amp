<?php
namespace Amp;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;

class Application extends \Symfony\Component\Console\Application {

  /**
   * @var string the base location for writable data
   */
  private $appDir;

  /**
   * @var array of string places to search for config files
   */
  private $configDirectories;

  /**
   * Primary entry point for execution of the standalone command.
   *
   * @param string $binDir
   *
   * @throws \Exception
   */
  public static function main($binDir) {
    if (getenv('AMPHOME')) {
      $appDir = getenv('AMPHOME');
    }
    else {
      $appDir = getenv('HOME') . DIRECTORY_SEPARATOR . '.amp';
    }
    $configDirectories = array(
      dirname($binDir) . '/app/defaults',
      $appDir,
    );

    $application = new Application('amp', '@package_version@', $appDir, $configDirectories);
    $application->setCatchExceptions(FALSE);
    $application->run();
  }

  /**
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  private $container;

  public function __construct($name, $version, $appDir, $configDirectories) {
    parent::__construct($name, $version);
    $this->appDir = $appDir;
    $this->configDirectories = $configDirectories;
    $this->loadContainer();
    $this->addCommands($this->createCommands());
  }

  public function loadContainer() {
    if (empty($this->appDir) || empty($this->configDirectories)) {
      throw new \Exception(__CLASS__ . ': Missing required properties (appDir, configDirectories)');
    }

    $container = new ContainerBuilder();
    $container->setParameter('app_dir', $this->appDir);
    $container->setParameter('amp_src_dir', dirname(__DIR__));
    $container->setParameter('log_dir', $this->appDir . DIRECTORY_SEPARATOR . 'log');
    $container->setParameter('apache_dir', $this->appDir . DIRECTORY_SEPARATOR . 'apache.d');
    //$container->setParameter('apache24_dir', $this->appDir . DIRECTORY_SEPARATOR . 'apache.d');
    $container->setParameter('apache_tpl', implode(DIRECTORY_SEPARATOR, array(
      __DIR__,
      'Templates',
      'apache-vhost.php'
    )));
    $container->setParameter('apache24_tpl', implode(DIRECTORY_SEPARATOR, array(
      __DIR__,
      'Templates',
      'apache24-vhost.php'
    )));
    $container->setParameter('nginx_dir', $this->appDir . DIRECTORY_SEPARATOR . 'nginx.d');
    $container->setParameter('nginx_tpl', implode(DIRECTORY_SEPARATOR, array(
      __DIR__,
      'Templates',
      'nginx-vhost.php'
    )));
    $container->setParameter('instances_yml', $this->appDir . DIRECTORY_SEPARATOR . 'instances.yml');
    $container->setParameter('config_yml', $this->appDir . DIRECTORY_SEPARATOR . 'services.yml');
    $container->setParameter('ram_disk_dir', $this->appDir . DIRECTORY_SEPARATOR . 'ram_disk');

    $fs = new Filesystem();
    $fs->mkdir(array(
      $this->appDir,
      $container->getParameter('log_dir'),
      $container->getParameter('apache_dir'),
      //$container->getParameter('apache24_dir'),
      $container->getParameter('nginx_dir'),
    ));

    $locator = new FileLocator($this->configDirectories);
    $loaderResolver = new LoaderResolver(array(
      new YamlFileLoader($container, $locator)
    ));
    $delegatingLoader = new DelegatingLoader($loaderResolver);
    foreach (array('services.yml') as $file) {
      $yamlUserFiles = $locator->locate($file, NULL, FALSE);
      foreach ($yamlUserFiles as $file) {
        $delegatingLoader->load($file);
      }
    }

    if (getenv('AMP_INSTANCES_TIMEOUT')) {
      $container->setParameter('instances_timeout', getenv('AMP_INSTANCES_TIMEOUT'));
    }

    $container->setAlias('mysql', 'mysql.' . $container->getParameter('mysql_type'));
    $container->setAlias('httpd', 'httpd.' . $container->getParameter('httpd_type'));
    $container->setAlias('perm', 'perm.' . $container->getParameter('perm_type'));
    $container->setAlias('ram_disk', 'ram_disk.' . $container->getParameter('ram_disk_type'));
    $this->container = $container;
  }

  /**
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   */
  public function getContainer() {
    return $this->container;
  }

  /**
   * Construct command objects
   *
   * FIXME: Move to services.yml
   *
   * @return array of Symfony Command objects
   */
  public function createCommands() {
    $commands = array();
    $commands[] = new \Amp\Command\ConfigCommand($this, NULL, $this->getContainer()->get('config.repository'));
    $commands[] = new \Amp\Command\ConfigGetCommand($this, NULL, $this->getContainer()->get('config.repository'));
    $commands[] = new \Amp\Command\ConfigSetCommand($this, NULL, $this->getContainer()->get('config.repository'));
    $commands[] = new \Amp\Command\ConfigResetCommand($this, NULL, $this->getContainer()->get('config.repository'));
    $commands[] = new \Amp\Command\DatadirCommand($this, NULL, $this->getContainer()->get('perm'));
    $commands[] = new \Amp\Command\TestCommand($this, NULL, $this->getContainer()->get('instances'));
    $commands[] = new \Amp\Command\CreateCommand($this, NULL, $this->getContainer()->get('instances'));
    $commands[] = new \Amp\Command\ShowCommand($this, NULL, $this->getContainer()->get('instances'));
    $commands[] = new \Amp\Command\ExportCommand($this, NULL, $this->getContainer()->get('instances'));
    $commands[] = new \Amp\Command\SqlCommand($this, NULL, $this->getContainer()->get('instances'));
    $commands[] = new \Amp\Command\DestroyCommand($this, NULL, $this->getContainer()->get('instances'));
    $commands[] = new \Amp\Command\CleanupCommand($this, NULL, $this->getContainer()->get('instances'));
    return $commands;
  }
}
