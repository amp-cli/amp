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
      ->setAliases(array('sql:cli'))
      ->setDescription('Open the SQL CLI')
      ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'The local path to the document root', getcwd())
      ->addOption('name', 'N', InputOption::VALUE_REQUIRED, 'Brief technical identifier for the service', '')
      ->addOption('eval', 'e', InputOption::VALUE_NONE, 'Evaluate preprocessing expressions')
      ->addOption('admin', 'a', InputOption::VALUE_NONE, 'Connect to the administrative data source')
      ->setHelp("
The \"sql\" command allows you to execute SQL interactively or through a pipe.

Note: When piping in SQL, the \"--eval\" option adds support for extra
pre-processing features. Specifically, it interpolates and escapes environment variables:

  export USERNAME=badguy
  echo 'DELETE FROM users WHERE username = @ENV[USERNAME]' | amp sql -e

The ENV expressions are prefixed to indicate their escaping rule:
  @ENV[FOO]    Produces an escaped version of string FOO
  #ENV[FOO]    Produces the numerical value of FOO (or fails)
  !ENV[FOO]    Produces the raw, unescaped string version of FOO
");
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
      "mysql " . $datasource->toMySQLArguments($this->getContainer()->getParameter('my_cnf_dir')),
      array(
        0 => $input->getOption('eval') ? array('pipe', 'r') : STDIN,
        1 => STDOUT,
        2 => STDERR,
      ),
      $pipes,
      $input->getOption('root')
    );

    if (is_resource($process) && $input->getOption('eval')) {
      fwrite($pipes[0], $this->filterSql(file_get_contents('php://stdin'), $datasource->createPDO()));
      fclose($pipes[0]);
    }

    return proc_close($process);
  }

  protected function filterSql($sql, \PDO $pdo) {
    $changed = preg_replace_callback('/([#!@])ENV\[([a-zA-Z0-9_]+)\]/', function ($matches)  use ($pdo) {
      $value = getenv($matches[2]);
      switch ($matches[1]) {
        case '!': // raw
          return $value;

        case '#': // numeric
          if (!is_numeric($value)) {
            throw new \RuntimeException("Environment variable " . $matches[2] . " is not numeric!");
          }
          return $value;

        case '@': // string
          return $pdo->quote($value);

        default:
          throw new \RuntimeException("Variable prefix not recognized.");
      }
    }, $sql);
    return $changed;
  }

}
