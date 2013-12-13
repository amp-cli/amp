<?php
namespace Amp\Command;

use Amp\Database\DatabaseManagementInterface;
use Amp\Instance;
use Amp\InstanceRepository;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCommand extends ContainerAwareCommand {

  /**
   * @var InstanceRepository
   */
  private $instances;

  /**
   * @var DatabaseManagementInterface
   */
  private $db;

  /**
   * @var Filesystem
   */
  private $fs;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct(\Amp\Application $app, $name = NULL, InstanceRepository $instances, DatabaseManagementInterface $db) {
    $this->instances = $instances;
    $this->db = $db;
    $this->fs = new Filesystem();
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('create')
      ->setDescription('Create a MySQL+HTTPD instance')
      ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'The local path to the document root', getcwd())
      ->addOption('name', 'N', InputOption::VALUE_REQUIRED, 'Brief technical identifier for the service (' . \Amp\Instance::NAME_REGEX . ')', '')
      ->addOption('no-url', NULL, InputOption::VALUE_NONE, 'Do not expose on the web')
      ->addOption('url', NULL, InputOption::VALUE_REQUIRED, 'Specify the preferred web URL for this service. (Omit to auto-generate)')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite any pre-existing httpd/mysql container');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $root = $this->fs->toAbsolutePath($input->getOption('root'));
    if (!$this->fs->exists($root)) {
      throw new \Exception("Failed to locate root: " . $root);
    } else {
      $input->setOption('root', $root);
    }
  }


  protected function execute(InputInterface $input, OutputInterface $output) {
    $instance = $this->instances->find(Instance::makeId($input->getOption('root'), $input->getOption('name')));
    if ($instance === NULL) {
      $instance = new Instance();
      $instance->setRoot($input->getOption('root'));
      $instance->setName($input->getOption('name'));
    }
    elseif (!$input->getOption('force')) {
      throw new \Exception("Cannot create instance. Use -f to existing overwrite.");
    }

    if ($input->getOption('url')) {
      $instance->setUrl($input->getOption('url'));
    } elseif (!$input->getOption('no-url')) {
      $instance->setUrl('http://localhost:FIXME');
    }

    $datasource = $instance->getDatasource();
    if ($datasource) {
      $this->db->dropDatabase($datasource);
      $this->db->createDatabase($datasource);
    }
    else {
      $datasource = $this->db->createDatasource(basename($instance->getRoot()) . $instance->getName());
      $this->db->createDatabase($datasource);
      $instance->setDatasource($datasource);
    }

    $this->instances->put($instance->getId(), $instance);
    $this->instances->save();

    if ($output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
      $this->export($instance->getRoot(), $instance->getName(), $output);
    }
  }

  protected function export($root, $name, OutputInterface $output) {
    $command = $this->getApplication()->find('export');
    $arguments = array(
      'command' => 'export',
      '--root' => $root,
      '--name' => $name,
    );
    return $command->run(new \Symfony\Component\Console\Input\ArrayInput($arguments), $output);
  }

}