<?php
namespace Amp\Command;

use Amp\Database\DatabaseManagementInterface;
use Amp\InstanceRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

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
      ->setDescription('Create an httpd/mysql container')
      ->addArgument('<name>', InputArgument::REQUIRED, 'Brief technical identifier for the service (' . \Amp\Instance::NAME_REGEX . ')')
      ->addOption('root', NULL, InputOption::VALUE_REQUIRED, 'The local path to the document root')
      ->addOption('no-root', NULL, InputOption::VALUE_NONE, 'Skip web-root')
      ->addOption('url', NULL, InputOption::VALUE_REQUIRED, 'The preferred web URL for this service')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite any pre-existing httpd/mysql container');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    if (!preg_match(\Amp\Instance::NAME_REGEX, $input->getArgument('<name>'))) {
      throw new \Exception('Malformed <name>');
    }

    $root = $this->toAbsolutePath($input->getOption('root'));
    if ($input->getOption('root')) {
      if (!$this->fs->exists($root)) {
        throw new \Exception("Failed to locate root: " . $root);
      }
    }
    elseif ($input->getOption('no-root')) {
      // ok
    }
    else {
      throw new \Exception("Missing option: --root=<path> or --no-root");
    }
  }

  /**
   * @param string $path
   * @return string updated $path
   */
  protected function toAbsolutePath($path) {
    if ($this->fs->isAbsolutePath($path)) {
      return $path;
    }
    else {
      return getcwd() . DIRECTORY_SEPARATOR . $path;
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $instance = $this->instances->find($input->getArgument('<name>'));
    if ($instance === NULL) {
      $instance = new \Amp\Instance($input->getArgument('<name>'));
    }
    elseif (!$input->getOption('force')) {
      throw new \Exception("Cannot create instance. Use -f to existing overwrite.");
    }

    if ($input->getOption('url')) {
      $instance->setUrl($input->getOption('url'));
    }

    $instance->setRoot($this->toAbsolutePath($input->getOption('root')));

    $datasource = $instance->getDatasource();
    if ($datasource) {
      $this->db->dropDatabase($datasource);
      $this->db->createDatabase($datasource);
    } else {
      $datasource = $this->db->createDatasource($instance->getName());
      $this->db->createDatabase($datasource);
      $instance->setDatasource($datasource);
    }

    $this->instances->put($instance->getName(), $instance);
    $this->instances->save();

    $this->export($input->getArgument('<name>'), $output);
  }

  protected function export($name, OutputInterface $output) {
    $command = $this->getApplication()->find('export');
    $arguments = array(
      'command' => 'export',
      '<name>' => $name,
    );
    return $command->run(new \Symfony\Component\Console\Input\ArrayInput($arguments), $output);
  }

}