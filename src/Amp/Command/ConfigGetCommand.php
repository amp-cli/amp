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
    $rows = array();
    foreach ($this->parameters as $key => $label) {
      $rows[] = array($key, $this->getContainer()->getParameter($key), $label);
    }

    $table = $this->getApplication()->getHelperSet()->get('table');
    $table->setHeaders(array('Key', 'Value', 'Description'));
    $table->setRows($rows);
    $table->render($output);
  }
}