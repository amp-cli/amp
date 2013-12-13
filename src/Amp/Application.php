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
use Symfony\Component\DependencyInjection\Scope;
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
   * @return
   */
  public static function main($binDir) {
    $appDir = $_ENV['HOME'] . DIRECTORY_SEPARATOR . '.amp';
    $configDirectories = array(
      dirname($binDir) . '/app/defaults',
      $appDir,
    );

    $application = new Application('amp', '@package_version@', $appDir, $configDirectories);
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
    $this->container = $this->createContainer();
    $this->addCommands($this->createCommands());
  }

  public function createContainer() {
    if (empty($this->appDir) || empty($this->configDirectories)) {
      throw new \Exception(__CLASS__ . ': Missing required properties (appDir, configDirectories)');
    }

    if (!is_dir($this->appDir)) {
      $fs = new Filesystem();
      $fs->mkdir($this->appDir);
    }

    $container = new ContainerBuilder();
    $container->setParameter('apache_dir', $this->appDir . DIRECTORY_SEPARATOR . 'apache.d');
    $container->setParameter('nginx_dir', $this->appDir . DIRECTORY_SEPARATOR . 'nginx.d');
    $container->setParameter('instances_yml', $this->appDir . DIRECTORY_SEPARATOR . 'instances.yml');

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

    $container->setAlias('mysql', 'mysql.' . $container->getParameter('mysql_type'));

    return $container;
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
    $configFile = $this->appDir . DIRECTORY_SEPARATOR . 'services.yml';
    $configParams = array(
      'apache_dir' => 'Directory which stores Apache config files',
      'nginx_dir' => 'Directory which stores nginx config files',
      'mysql_type' => 'How to connect to MySQL admin (cli, dsn, linuxRamDisk)',
      'mysql_dsn' => 'Administrative connection details (for use with "dsn")',
    );

    $commands = array();
    $commands[] = new \Amp\Command\ConfigGetCommand($this, NULL, $configParams);
    $commands[] = new \Amp\Command\ConfigSetCommand($this, NULL, $configFile, $configParams);
    $commands[] = new \Amp\Command\ConfigResetCommand($this, NULL, $configFile, $configParams);
    $commands[] = new \Amp\Command\CreateCommand($this, NULL, $this->getContainer()->get('instances'), $this->getContainer()->get('mysql'));
    $commands[] = new \Amp\Command\ShowCommand($this, NULL, $this->getContainer()->get('instances'));
    $commands[] = new \Amp\Command\ExportCommand($this, NULL, $this->getContainer()->get('instances'));
    $commands[] = new \Amp\Command\DestroyCommand($this, NULL, $this->getContainer()->get('instances'), $this->getContainer()->get('mysql'));
    $commands[] = new \Amp\Command\CleanupCommand($this, NULL, $this->getContainer()->get('instances'), $this->getContainer()->get('mysql'));
    return $commands;
  }
}