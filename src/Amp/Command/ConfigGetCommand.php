<?php
namespace Amp\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigGetCommand extends ContainerAwareCommand {

  /**
   * @var array ($key => $label)
   */
  private $parameters;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct(\Amp\Application $app, $name = NULL, $parameters = NULL) {
    $this->parameters = $parameters;
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('config:get')
      ->setDescription('Get configuration options');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    foreach ($this->parameters as $key => $label) {
      $output->writeln(sprintf("%-15s: %s", $key, $this->getContainer()->getParameter($key)));
    }
  }
}