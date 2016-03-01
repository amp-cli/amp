<?php
namespace Amp\Command;

use Amp\ConfigRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigGetCommand extends ContainerAwareCommand {

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
      ->setName('config:get')
      ->setDescription('Get configuration options');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $rows = array();
    foreach ($this->config->getParameters() as $key) {
      $rows[] = array($key, $this->getContainer()->getParameter($key), $this->config->getDescription($key));
    }

    $table = $this->getApplication()->getHelperSet()->get('table');
    $table->setHeaders(array('Key', 'Value', 'Description'));
    $table->setRows($rows);
    $table->render($output);
  }

}
