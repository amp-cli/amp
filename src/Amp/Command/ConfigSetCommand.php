<?php
namespace Amp\Command;

use Amp\ConfigRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigSetCommand extends ContainerAwareCommand {

  /**
   * @var ConfigRepository
   */
  private $config;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param ConfigRepository $config
   */
  public function __construct(\Amp\Application $app, $name = NULL, ConfigRepository $config = NULL) {
    $this->config = $config;
    parent::__construct($app, $name);
  }


  protected function configure() {
    $this
      ->setName('config:set')
      ->setDescription('Set configuration value');
    foreach ($this->config->getParameters() as $key) {
      $this->addOption($key, NULL, InputOption::VALUE_REQUIRED, $this->config->getDescription($key));
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    foreach ($this->config->getParameters() as $key) {
      if ($input->getOption($key) !== NULL) {
        $this->config->setParameter($key, $input->getOption($key));
        $this->getContainer()->setParameter($key, $input->getOption($key));
      }
    }
    $this->config->save();
  }

}