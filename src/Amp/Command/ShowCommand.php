<?php
namespace Amp\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends ContainerAwareCommand {

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   */
  public function __construct(\Amp\Application $app, $name = NULL) {
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('show')
      ->setDescription('Show a list of all containers');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $instances = $this->getContainer()->get('instances');
    if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
      $rows = array();
      foreach ($instances->findAll() as $instance) {
        $rows[] = array('root', $instance->getRoot());
        $rows[] = array('name', $instance->getName());
        $rows[] = array('dsn', $instance->getDsn());
        $rows[] = array('url', $instance->getUrl());
        $rows[] = array('visibility', $instance->getVisibility());
        $rows[] = array('', '');
      }

      $table = new Table($output);
      $table->setHeaders(array('Property', 'Value'));
      $table->setRows($rows);
      $table->render();
    }
    else {
      $rows = array();
      foreach ($instances->findAll() as $instance) {
        $rows[] = array(
          $instance->getRoot(),
          $instance->getName(),
          $instance->getDsn() ? 'y' : '',
          $instance->getUrl() ? 'y' : '',
          $instance->getVisibility(),
        );
      }

      $table = new Table($output);
      $table->setHeaders(array('Root', 'Name', 'DB', 'Web', 'Visibility'));
      $table->setRows($rows);
      $table->render();
      $output->writeln('For more detailed info, use "amp show -v" or "amp export [--root=X] [---name=X]');
    }
    return 0;
  }

}
