<?php
namespace Amp\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigSetCommand extends ContainerAwareCommand {

  /**
   * @var string
   */
  private $configFile;

  /**
   * @var array ($key => $label)
   */
  private $parameters;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct(\Amp\Application $app, $name = NULL, $configFile = NULL, $parameters = NULL) {
    $this->configFile = $configFile;
    $this->parameters = $parameters;
    parent::__construct($app, $name);
  }


  protected function configure() {
    $this
      ->setName('config:set')
      ->setDescription('Set configuration value');
    foreach ($this->parameters as $key => $label) {
      $this->addOption($key, NULL, InputOption::VALUE_REQUIRED, $label);
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if (file_exists($this->configFile)) {
      $config = Yaml::parse(file_get_contents($this->configFile));
    }
    else {
      $config = $this->createDefaultConfig();
    }

    foreach ($this->parameters as $key => $label) {
      if ($input->getOption($key) !== NULL) {
        $config['parameters'][$key] = $input->getOption($key);
        $this->getContainer()->setParameter($key, $input->getOption($key));
      }
    }

    file_put_contents($this->configFile, Yaml::dump($config));
  }

  protected function createDefaultConfig() {
    return array(
      'parameters' => array(),
      'services' => array(),
    );
  }
}