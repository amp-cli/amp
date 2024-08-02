<?php
namespace Amp\Command;

use Amp\Database\Datasource;
use Amp\Instance;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestCommand extends ContainerAwareCommand {

  /**
   * @var \Symfony\Component\Templating\EngineInterface
   */
  private $templateEngine;

  /**
   * A random value that should be returned by canary, if it runs successfully
   *
   * @var string
   */
  private $expectedResponse;

  /**
   * @var \Amp\Util\Filesystem
   */
  private $fs;

  /**
   * @param \Amp\Application $app
   * @param string|null $name
   */
  public function __construct(\Amp\Application $app, $name = NULL) {
    $this->fs = new Filesystem();
    $this->expectedResponse = 'response-code-' . \Amp\Util\StringUtil::createRandom(10);
    parent::__construct($app, $name);
    $this->templateEngine = $this->getContainer()->get('template.engine');
  }

  protected function configure() {
    $this
      ->setName('test')
      ->setDescription('Test that amp is working')
      ->addOption('url', NULL, InputOption::VALUE_REQUIRED, 'The URL at which to deploy the test app', 'http://localhost:7979');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    /** @var \Amp\InstanceRepository $instances */
    $instances = $this->getContainer()->get('instances');
    $instances->lock();

    $defaultUrl = $input->getOption('url');

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
    // assume previous tests may have failed badly
      '--force' => 1,
      '--url' => $defaultUrl,
    ));
    $output->writeln("");
    // force reload
    $instances->load();
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
      $output->writeln("<comment>Tips for common issues:</comment>");
      $output->writeln("<comment> - (Re)run \"amp config\"</comment>");

      $httpdType = $this->getContainer()->getParameter('httpd_type');
      $output->writeln("<comment> - Double-check the httpd_type ($httpdType) along with any displayed instructions.</comment>");
      if (!$httpdType || $httpdType === 'none') {
        $output->writeln("<comment> - In absence of a known httpd_type, you will be responsible for configuring vhosts. Ensure that the vhost ($defaultUrl) is configured.</comment>");
      }

      $restartCommand = $this->getContainer()->getParameter('httpd_restart_command');
      $output->writeln("<comment> - Double-check the httpd_restart_command.</comment>");
      if (!$restartCommand || $restartCommand === 'NONE') {
        $output->writeln("<comment> - In absence of the httpd_restart_command, you will be responsible for any restarts. Cycle through and alternately run \"amp test\" and restart httpd manually.</comment>");
      }

      $output->writeln("<comment> - (Re)run \"amp test\"</comment>");
    }

    if (!rmdir($dataDir)) {
      $output->writeln("<error>Failed to clean up data directory: $dataDir</error>");
    }

    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return array
   */
  protected function createCanaryFiles(OutputInterface $output) {
    // Create empty web dir
    $root = $this->getContainer()->getParameter('app_dir') . DIRECTORY_SEPARATOR . 'canary';
    if (!$this->fs->exists($root)) {
      $this->fs->mkdir($root);
    }

    // Create empty data dir
    $dataDirCode = \Amp\Util\StringUtil::createRandom(32);
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
        'content' => http_build_query($postData),
      ),
    );
    $context = stream_context_create($opts);
    $result = file_get_contents($url, FALSE, $context);
    return $result;
  }

}
