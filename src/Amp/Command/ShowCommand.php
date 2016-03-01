<?php
namespace Amp\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends ContainerAwareCommand {

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct(\Amp\Application $app, $name = NULL) {
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('show')
      ->setDescription('Show a list of all containers');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $instances = $this->getContainer()->get('instances');
    if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
      $rows = array();
      foreach ($instances->findAll() as $instance) {
        $rows[] = array('root', $instance->getRoot());
        $rows[] = array('name', $instance->getName());
        $rows[] = array('dsn', $instance->getDsn());
        $rows[] = array('url', $instance->getUrl());
        $rows[] = array('', '');
      }

      /** @var $table \Symfony\Component\Console\Helper\TableHelper */
      $table = $this->getApplication()->getHelperSet()->get('table');
      $table->setHeaders(array('Property', 'Value'));
      $table->setRows($rows);
      $table->render($output);
    }
    else {
      $rows = array();
      foreach ($instances->findAll() as $instance) {
        $rows[] = array(
          $instance->getRoot(),
          $instance->getName(),
          $instance->getDsn() ? 'y' : '',
          $instance->getUrl() ? 'y' : '',
        );
      }

      /** @var $table \Symfony\Component\Console\Helper\TableHelper */
      $table = $this->getApplication()->getHelperSet()->get('table');
      $table->setHeaders(array('Root', 'Name', 'DB', 'Web'));
      $table->setRows($rows);
      $table->render($output);
      $output->writeln('For more detailed info, use "amp show -v" or "amp export [--root=X] [---name=X]');
    }
  }
}
