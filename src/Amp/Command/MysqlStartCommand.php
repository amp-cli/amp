<?php
namespace Amp\Command;

use Amp\Database\MySQLRAMServer;
use Amp\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MysqlStartCommand extends ContainerAwareCommand {

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
    parent::__construct($app, $name);
  }

  protected function configure() {
    $this
      ->setName('mysql:start')
      ->setDescription('(For mysql_ram_disk only) Start the ramdisk and MySQL services');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $instances = $this->getContainer()->get('instances');
    $instances->lock();

    $container = $this->getContainer();

    /** @var \Amp\Database\MySQLRAMServer $db */
    $db = $container->get('db');
    if (!$db instanceof MySQLRAMServer) {
      throw new \Exception("This command only applies if you use mysql_ramdisk");
    }

    $db->init();

    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
      $output->writeln("<comment>MySQL PID file is $db->mysqld_pid_path</comment>");
    }

    if (!file_exists($db->mysqld_pid_path)) {
      $output->writeln("PID file not found ($db->mysqld_pid_path). Perhaps mysqld isn't running?");
      return 1;
    }
    $pid = trim(file_get_contents($db->mysqld_pid_path));
    $output->writeln($pid, OutputInterface::OUTPUT_PLAIN);
    return 0;
  }

}
