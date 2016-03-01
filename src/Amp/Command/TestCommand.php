<?php
namespace Amp\Command;

use Amp\Database\Datasource;
use Amp\Instance;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Templating\EngineInterface;

class TestCommand extends ContainerAwareCommand {

  /**
   * @var EngineInterface
   */
  private $templateEngine;

  /**
   * @var string a random value that should be returned by canary
   * if it runs successfully
   */
  private $expectedResponse;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   */
  public function __construct(\Amp\Application $app, $name = NULL) {
    $this->fs = new Filesystem();
    $this->expectedResponse = 'response-code-' . \Amp\Util\String::createRandom(10);
    parent::__construct($app, $name);
    $this->templateEngine = $this->getContainer()->get('template.engine');
  }

  protected function configure() {
    $this
      ->setName('test')
      ->setDescription('Test that amp is working');
  }


  protected function execute(InputInterface $input, OutputInterface $output) {
    /** @var \Amp\InstanceRepository $instances */
    $instances = $this->getContainer()->get('instances');
    $instances->lock();

    // Display help text
    //$output->write($this->templateEngine->render('testing.php', array(
    //  'apache_dir' => $this->getContainer()->getParameter('apache_dir'),
    //  'nginx_dir' => $this->getContainer()->getParameter('nginx_dir'),
    //)));

    // Setup test instance
    $output->writeln("<info>Create test application</info>");
    list ($root, $dataDir) = $this->createCanaryFiles($output);
    $this->doCommand($output, OutputInterface::VERBOSITY_NORMAL, 'create', array(
      '--root' => $root,
      '--force' => 1, // assume previous tests may have failed badly
      '--url' => 'http://localhost:7979'
    ));
    $output->writeln("");
    $instances->load(); // force reload
    $instance = $instances->find(Instance::makeId($root, ''));
    $this->createConfigFile($instance->getRoot() . '/config.php', $instance->getDatasource(), $dataDir);

    // Connect to test instance
    $output->writeln("<info>Connect to test application</info>");
    $output->writeln("<comment>Expect response: \"{$this->expectedResponse}\"</comment>");

    $response = $this->doPost($instance->getUrl() . '/index.php', array(
      'exampleData' => 'foozball',
    ));

    if ($response == $this->expectedResponse) {
      $output->writeln("<info>Received expected response</info>");

      // Tear down test instance
      // Skip teardown; this allows us to preserve the port-number
      // across multiple executions.
      //$output->writeln("<info>Cleanup test application</info>");
      //$this->doCommand($output, OutputInterface::VERBOSITY_NORMAL, 'destroy', array(
      //  '--root' => $root,
      //));
    }
    else {
      $output->writeln("<error>Received incorrect response: \"$response\"</error>");
      $output->writeln("<comment>Tip: Try running \"amp config\" and/or restarting the webserver.</comment>");
    }

    if (!rmdir($dataDir)) {
      $output->writeln("<error>Failed to clean up data directory: $dataDir</error>");
    }
  }

  /**
   * @param string $template path of the example canary script
   * @return string, root path of the canary web app
   */
  protected function createCanaryFiles(OutputInterface $output) {
    // Create empty web dir
    $root = $this->getContainer()->getParameter('app_dir') . DIRECTORY_SEPARATOR . 'canary';
    if (!$this->fs->exists($root)) {
      $this->fs->mkdir($root);
    }

    // Create empty data dir
    $dataDirCode = \Amp\Util\String::createRandom(32);
    $dataDir = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $dataDirCode;
    $this->doCommand($output, OutputInterface::VERBOSITY_NORMAL, 'datadir', array(
      'path' => array($dataDir),
    ));

    // Create PHP code
    $content = $this->templateEngine->render('canary.php', array(
      'expectedResponse' => $this->expectedResponse,
      'dataDir' => $dataDir,
    ));
    $this->fs->dumpFile($root . DIRECTORY_SEPARATOR . 'index.php', $content);

    return array($root, $dataDir);
  }

  protected function createConfigFile($file, Datasource $datasource, $dataDir) {
    $config = array(
      'dsn' => $datasource->toPDODSN(),
      'user' => $datasource->getUsername(),
      'pass' => $datasource->getPassword(),
      'dataDir' => $dataDir,
    );
    $this->fs->dumpFile($file, "<?php\nreturn " . var_export($config, 1) . ";");
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
