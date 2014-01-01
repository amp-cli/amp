<?php
namespace Amp\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ContainerAwareCommand extends Command {
  /**
   * @var \Amp\Application
   */
  private $app;

  public function __construct(\Amp\Application $app, $name = NULL) {
    parent::__construct($name);
    $this->app = $app;
  }

  protected function doCommand(OutputInterface $output, $verbosity, $command, $args) {
    $oldVerbosity = $output->getVerbosity();
    $output->setVerbosity($verbosity);

    $c = $this->getApplication()->find($command);
    $input = new \Symfony\Component\Console\Input\ArrayInput(
      array_merge(array('command' => $c), $args)
    );
    $c->run($input, $output);

    $output->setVerbosity($oldVerbosity);
  }

  public function getContainer() {
    return $this->app->getContainer();
  }
}
