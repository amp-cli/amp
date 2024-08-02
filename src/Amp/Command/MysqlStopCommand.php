<?php
namespace Amp\Command;

use Amp\Database\DatabaseManagementInterface;
use Amp\Database\MySQLRAMServer;
use Amp\Instance;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MysqlStopCommand extends ContainerAwareCommand {

  /**
   * @var Filesystem
   */
  private $fs;

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
      ->setName('mysql:stop')
      ->setDescription('(For mysql_ram_disk only) Stop the ramdisk-based MySQL service');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $instances = $this->getContainer()->get('instances');
    $instances->lock();

    $container = $this->getContainer();

    /** @var MySQLRAMServer $db */
    $db = $container->get('db');
    if (!$db instanceof MySQLRAMServer) {
      throw new \Exception("This command only applies if you use mysql_ramdisk");
    }

    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
      $output->writeln("MySQL PID file is $db->mysqld_pid_path");
    }

    if (!file_exists($db->mysqld_pid_path)) {
      $output->writeln("PID file not found ($db->mysqld_pid_path). Perhaps mysqld isn't running?");
      return 1;
    }

    $pid = trim(file_get_contents($db->mysqld_pid_path));
    $output->writeln("Killing mysqld ($pid)");
    $result = posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
    if (!$result) {
      $output->getErrorOutput()->writeln("Fasdf");
    }

    return 0;
  }

}
