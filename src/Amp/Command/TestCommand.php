<?php
namespace Amp\Command;

use Amp\Instance;
use Amp\InstanceRepository;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends ContainerAwareCommand {

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
    $this->fs = new Filesystem();
    $this->instances = $instances;
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('test')
      ->setDescription('Test that amp is working');
  }


  protected function execute(InputInterface $input, OutputInterface $output) {
    $root = $this->createCanaryApp();

    // Setup test instance
    $this->doCommand($output, OutputInterface::VERBOSITY_QUIET, 'create', array(
      '--root' => $root,
      '--force' => 1, // assume previous tests may have failed badly
    ));

    // Connect to test instance
    $this->instances->load(); // force reload
    $instance = $this->instances->find(Instance::makeId($root, ''));
    $result = $this->doPost($instance->getUrl() . '/index.php', array(
      'dsn' => $instance->getDsn(),
    ));
    print_r(array('post result' => $result));

    // Tear down test instance
    $this->doCommand($output, OutputInterface::VERBOSITY_NORMAL, 'destroy', array(
      '--root' => $root,
    ));
  }

  /**
   * @param string $template path of the example canary script
   * @return string, root path of the canary web app
   */
  protected function createCanaryApp() {
    $template = $this->fs->toAbsolutePath(__DIR__ . '/../../../web-canary/index.php');
    $root = $this->getContainer()->getParameter('app_dir') . DIRECTORY_SEPARATOR . 'canary';
    if (!$this->fs->exists($root)) {
      $this->fs->mkdir($root);
    }
    $this->fs->copy($template, $root . DIRECTORY_SEPARATOR . 'index.php', TRUE);
    return $root;
  }

  protected function doCommand(OutputInterface $output, $verbosity, $command, $args) {
    $oldVerbosity = $output->getVerbosity();
    $output->setVerbosity($verbosity);

    $c = $this->getApplication()->find($command);
    $input = new \Symfony\Component\Console\Input\ArrayInput(
      array_merge(array('command' => $c), $args)
    );
    $c->run($input, $output);

    $output->setVerbosity($oldVerbosity);
  }

  protected function doPost($url, $postData) {
    $opts = array(
      'http' =>
      array(
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postData
      )
    );
    $context = stream_context_create($opts);
    $result = file_get_contents($url, FALSE, $context);
    return $result;
  }
}
