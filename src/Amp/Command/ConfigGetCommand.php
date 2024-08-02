<?php
namespace Amp\Command;

use Amp\ConfigRepository;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigGetCommand extends ContainerAwareCommand {

  /**
   * @var \Amp\ConfigRepository
   */
  private $config;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param \Amp\ConfigRepository|null $config
   */
  public function __construct(\Amp\Application $app, $name = NULL, ConfigRepository $config = NULL) {
    $this->config = $config;
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('config:get')
      ->setDescription('Get configuration options')
      ->addArgument('key', InputArgument::OPTIONAL, 'Name of a configuration field');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($input->getArgument('key')) {
      $output->writeln($this->getContainer()->getParameter($input->getArgument('key')));
      return 0;
    }

    $rows = array();
    foreach ($this->config->getParameters() as $key) {
      $rows[] = array($key, $this->getContainer()->getParameter($key), $this->config->getDescription($key));
    }

    $table = new Table($output);
    $table->setHeaders(array('Key', 'Value', 'Description'));
    $table->setRows($rows);
    $table->render();

    return 0;
  }

}
