<?php
namespace Amp\Command;

use Amp\ConfigRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigResetCommand extends ContainerAwareCommand {

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
      ->setName('config:reset')
      ->setDescription('Reset a configuration value')
      ->addOption('all', 'a', InputOption::VALUE_NONE, 'Reset all listed parameters');
    foreach ($this->config->getParameters() as $key) {
      $this->addOption($key, NULL, InputOption::VALUE_NONE, $this->config->getDescription($key));
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $found = FALSE;
    foreach ($this->config->getParameters() as $key) {
      if ($input->getOption($key) || $input->getOption('all')) {
        $this->config->unsetParameter($key);
        $found = TRUE;
      }
    }

    if ($found) {
      $this->config->save();
    }
    else {
      $output->writeln('<error>No properties specified</error>');
    }
  }
}