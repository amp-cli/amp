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

class DestroyCommand extends ContainerAwareCommand {

  /**
   * @var Filesystem
   */
  private $fs;

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
      ->setName('destroy')
      ->setDescription('Destroy a MySQL+HTTPD instance')
      ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'The local path to the document root', getcwd())
      ->addOption('name', 'N', InputOption::VALUE_REQUIRED, 'Brief technical identifier for the service', '');
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
    $instance = $this->instances->find(Instance::makeId($input->getOption('root'), $input->getOption('name')));
    if (!$instance) {
      return;
    }

    $this->instances->remove($instance->getId());
    $this->instances->save();
  }

}