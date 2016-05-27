<?php
namespace Amp\Command;

use Amp\Instance;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class SqlCommand extends ContainerAwareCommand {

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   */
  public function __construct(\Amp\Application $app, $name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('sql')
      ->setDescription('Open the SQL CLI')
      ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'The local path to the document root', getcwd())
      ->addOption('name', 'N', InputOption::VALUE_REQUIRED, 'Brief technical identifier for the service', '')
      ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Connect to the administrative data source');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $root = $this->fs->toAbsolutePath($input->getOption('root'));
    if (!$this->fs->exists($root)) {
      throw new \Exception("Failed to locate root: " . $root);
    }
    else {
      $input->setOption('root', $root);
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($input->getOption('admin')) {
      $db = $this->getContainer()->get('db');
      if (is_callable(array($db, 'getAdminDatasource'))) {
        $datasource = $db->getAdminDatasource();
      }
      else {
        throw new \Exception("This database does not provide access to an administrative datasource.");
      }
    }
    else {
      $instance = $this->getContainer()->get('instances')->find(Instance::makeId($input->getOption('root'), $input->getOption('name')));
      if (!$instance) {
        throw new \Exception("Failed to locate instance: " . Instance::makeId($input->getOption('root'), $input->getOption('name')));
      }
      $datasource = $instance->getDatasource();
    }

    $process = proc_open(
      "mysql " . $datasource->toMySQLArguments(),
      array(
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
      ),
      $pipes,
      $input->getOption('root')
    );
    return proc_close($process);
  }

}
