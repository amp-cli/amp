<?php
namespace Amp\Command;

use Amp\InstanceRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends ContainerAwareCommand {

  /**
   * @var InstanceRepository
   */
  private $instances;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct(\Amp\Application $app, $name = NULL, InstanceRepository $instances) {
    $this->instances = $instances;
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('show')
      ->setDescription('Show a list of all containers');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $rows = array();
    foreach ($this->instances->findAll() as $instance) {
      $rows[] = array($instance->getRoot(), $instance->getName(), $instance->getDsn(), $instance->getUrl());
    }

    $table = $this->getApplication()->getHelperSet()->get('table');
    $table->setHeaders(array('Root', 'Name', 'DSN', 'URL'));
    $table->setRows($rows);
    $table->render($output);
  }
}