<?php
namespace Amp\Command;

use Symfony\Component\Console\Command\Command;

abstract class ContainerAwareCommand extends Command {
  /**
   * @var \Amp\Application
   */
  private $app;

  public function __construct(\Amp\Application $app, $name = NULL) {
    parent::__construct($name);
    $this->app = $app;
  }

  public function getContainer() {
    return $this->app->getContainer();
  }
}