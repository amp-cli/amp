<?php
namespace Amp\Command;

use Amp\ConfigRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigSetCommand extends ContainerAwareCommand {

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
      ->setName('config:set')
      ->setDescription('Set configuration value');
    foreach ($this->config->getParameters() as $key) {
      $this->addOption($key, NULL, InputOption::VALUE_REQUIRED, $this->config->getDescription($key));
    }

    // Deprecated options.
    $this->addOption('mysql_type', NULL, InputOption::VALUE_REQUIRED, 'Deprecated. See db_type.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    // Deprecated options.
    if ($input->getOption('mysql_type') !== NULL) {
      if ($input->getOption('db_type') === NULL) {
        $input->setOption('db_type', 'mysql_' . $input->getOption('mysql_type'));
        $output->writeln('<error>Option "--mysql_type" is deprecated and will be removed in the future. Please use "--db_type" instead.</error>');
      }
      else {
        throw new \RuntimeException('Conflicting input. Use "--db_type" instead of "--mysql_type".');
      }
    }

    // Main options.
    foreach ($this->config->getParameters() as $key) {
      if ($input->getOption($key) !== NULL) {
        $this->config->setParameter($key, $input->getOption($key));
        $this->getContainer()->setParameter($key, $input->getOption($key));
      }
    }
    $this->config->save();
  }

}
