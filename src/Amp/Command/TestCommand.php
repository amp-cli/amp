<?php
namespace Amp\Command;

use Amp\Instance;
use Amp\InstanceRepository;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Templating\EngineInterface;

class TestCommand extends ContainerAwareCommand {

  /**
   * @var InstanceRepository
   */
  private $instances;

  /**
   * @var EngineInterface
   */
  private $templateEngine;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct(\Amp\Application $app, $name = NULL, InstanceRepository $instances) {
    $this->fs = new Filesystem();
    $this->instances = $instances;
    parent::__construct($app, $name);
    $this->templateEngine = $this->getContainer()->get('template.engine');
  }

  protected function configure() {
    $this
      ->setName('test')
      ->setDescription('Test that amp is working');
  }


  protected function execute(InputInterface $input, OutputInterface $output) {
    // Display help text
    $output->write($this->templateEngine->render('testing.php', array(
      'apache_dir' => $this->getContainer()->getParameter('apache_dir'),
      'nginx_dir' => $this->getContainer()->getParameter('nginx_dir'),
    )));

    // Setup test instance
    $output->writeln("<info>Create test application</info>");
    $root = $this->createCanaryCodebase();
    $this->doCommand($output, OutputInterface::VERBOSITY_NORMAL, 'create', array(
      '--root' => $root,
      '--force' => 1, // assume previous tests may have failed badly
      '--url' => 'http://localhost:7979'
    ));
    $output->writeln("");

    // Connect to test instance
    $output->writeln("<info>Connect to test application</info>");
    $this->instances->load(); // force reload
    $instance = $this->instances->find(Instance::makeId($root, ''));
    $response = $this->doPost($instance->getUrl() . '/index.php', array(
      'dsn' => $instance->getDsn(),
    ));

    if ($response == 'OK') {
      $output->writeln("<info>Connect to test application: OK</info>");

      // Tear down test instance
      // Skip teardown; this allows us to preserve the port-number
      // across multiple executions.
      //$output->writeln("<info>Cleanup test application</info>");
      //$this->doCommand($output, OutputInterface::VERBOSITY_NORMAL, 'destroy', array(
      //  '--root' => $root,
      //));
    }
    else {
      $output->writeln("<error>Connect to test application: Failed</error>");
      $output->writeln("Response: $response");
    }
  }

  /**
   * @param string $template path of the example canary script
   * @return string, root path of the canary web app
   */
  protected function createCanaryCodebase() {
    $content = $this->templateEngine->render('canary.php', array(
      'autoloader' => $this->fs->toAbsolutePath(__DIR__ . '/../../../vendor/autoload.php')
    ));
    $root = $this->getContainer()->getParameter('app_dir') . DIRECTORY_SEPARATOR . 'canary';
    if (!$this->fs->exists($root)) {
      $this->fs->mkdir($root);
    }
    $this->fs->dumpFile($root . DIRECTORY_SEPARATOR . 'index.php', $content);
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
      'http' => array(
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => http_build_query($postData)
      )
    );
    $context = stream_context_create($opts);
    $result = file_get_contents($url, FALSE, $context);
    return $result;
  }
}
