<?php
namespace Amp\Command;

use Amp\Instance;
use Amp\InstanceRepository;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class ExportCommand extends ContainerAwareCommand {

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
    $this->fs = new Filesystem();
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('export')
      ->setDescription('Export details about a MySQL+HTTPD instance for use in bash')
      ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'The local path to the document root', getcwd())
      ->addOption('name', 'N', InputOption::VALUE_REQUIRED, 'Brief technical identifier for the service', '')
      ->addOption('prefix', NULL, InputOption::VALUE_REQUIRED, 'Prefix to place in front of each outputted variable', 'AMP_')
      ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output to file instead of stdout');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $root = $this->fs->toAbsolutePath($input->getOption('root'));
    if (!$this->fs->exists($root)) {
      throw new \Exception("Failed to locate root: " . $root);
    } else {
      $input->setOption('root', $root);
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $input->getOption('prefix'))) {
      throw new \Exception('Malformed prefix');
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $instance = $this->instances->find(Instance::makeId($input->getOption('root'), $input->getOption('name')));
    if (!$instance) {
      throw new \Exception("Failed to locate instance: " . Instance::makeId($input->getOption('root'), $input->getOption('name')));
    }
    $prefix = $input->getOption('prefix');
    $dsnParts = \DB\DSN::parseDSN($instance->getDsn());
    $output_file_path = $input->getOption('output-file');
    if ($output_file_path != '') {
      $output_file = fopen($output_file_path, "w");
      $output = new StreamOutput($output_file);
    }

    $envVars = array(
      "{$prefix}URL" => $instance->getUrl(),
      "{$prefix}ROOT" => $instance->getRoot(),
      "{$prefix}DB_DSN" => $instance->getDsn(),
      "{$prefix}DB_USER" => $dsnParts['username'],
      "{$prefix}DB_PASS" => $dsnParts['password'],
      "{$prefix}DB_HOST" => $dsnParts['hostspec'],
      "{$prefix}DB_PORT" => $dsnParts['port'],
      "{$prefix}DB_NAME" => $dsnParts['database'],
    );
    foreach ($envVars as $var => $value) {
      $output->writeln($var . '=' . escapeshellarg($value));
    }
    // $output->writeln('export ' . implode(' ', array_keys($envVars)));
  }
}
