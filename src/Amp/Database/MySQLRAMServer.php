<?php
namespace Amp\Database;

use Amp\Database\Datasource;
use Amp\Database\MySQL;
use Amp\Util\Path;

class MySQLRAMServer extends MySQL {
  public $mysqld_base_command;
  public $mysql_data_path;
  public $mysql_admin_user = 'root';
  public $mysql_admin_password = 'root';
  public $port;
  public $tmp_path;

  /**
   * @var \Amp\RamDisk\RamDiskInterface
   */
  public $ram_disk;

  /**
   * @var \Amp\Database\MySQLRAMServer\AppArmor
   */
  public $app_armor;


  public function buildMySQLDBaseCommand() {
    $this->mysqld_base_command = "mysqld --no-defaults --tmpdir={$this->tmp_path} --datadir={$this->mysql_data_path} --port={$this->port} --socket={$this->mysql_socket_path} --pid-file={$this->mysqld_pid_file_path}";
  }

  public function createDatasource($hint) {
    if (!$this->ram_disk->isMounted()) {
      $this->ram_disk->mount();
      Path::mkdir_p_if_not_exists(Path::join($this->mysql_data_path, 'mysql'));
      Path::mkdir_p_if_not_exists($this->tmp_path);
    }
    if ($this->app_armor) {
      $this->app_armor->setTmpPath($this->tmp_path);  // TODO: move to services.yml or remove entirely
      if (!$this->app_armor->isConfigured()) {
        $this->app_armor->configure();
      }
    }
    $this->buildMySQLDBaseCommand();
    if (!$this->isRunning()) {
      $this->runCommand("echo \"use mysql;\" > {$this->tmp_path}/install_mysql.sql");
      $this->runCommand("cat /usr/share/mysql/mysql_system_tables.sql /usr/share/mysql/mysql_system_tables_data.sql >> {$this->tmp_path}/install_mysql.sql");
      $this->runCommand("{$this->mysqld_base_command} --log-warnings=0 --bootstrap --loose-skip-innodb --max_allowed_packet=8M --default-storage-engine=myisam --net_buffer_length=16K < {$this->tmp_path}/install_mysql.sql");
      $this->runCommand("{$this->mysqld_base_command} > {$this->tmp_path}/mysql-drupal-test.log 2>&1 &");
      $i = 0;
      if (!file_exists($this->mysql_socket_path) or $i > 9) {
        $i++;
        sleep(1);
      }
      if ($i == 9) {
        throw new Exception("There was a problem starting the MySQLRAM server. We expect to see a socket file at {$this->mysql_socket_path} but it hasn't appeared after 10 waiting seconds.");
      }
      $i = 0;
      $last_exception = NULL;
      while ($i < 9) {
        $i++;
        try {
          $this->runCommand("mysqladmin --socket={$this->mysql_socket_path} --user=root --password='' password '{$this->mysql_admin_password}'");
          break;
        }
        catch (\Exception $e) {
          $last_exception = $e;
        }
        sleep(1);
      }
      if ($i == 9) {
        throw $last_exception;
      }
    }
    $pass = \Amp\Util\String::createRandom(16);
    $user = \Amp\Util\String::createHintedRandom($hint, 16, 5, 'abcdefghijklmnopqrstuvwxyz0123456789');

    $datasource = new Datasource();
    $datasource->setDriver($this->adminDatasource->getDriver());
    $datasource->setHost('127.0.0.1');
    $datasource->setPort($this->port);
    $datasource->setSocketPath($this->mysql_socket_path);
    $datasource->setUsername($user);
    $datasource->setPassword($pass);
    $datasource->setDatabase($user);

    return $datasource;
  }

  public function isRunning() {
    $port_checker = new \Amp\Util\PortChecker();
    return $port_checker->checkHostPort('localhost', $this->port);
  }

  public function runCommand($command, $options = array()) {
    $options['print_command'] = true;
    return \Amp\Util\Shell::run($command);
  }

  public function setAdminDsn($dns) {
    throw new Exception("MySQLinuxRAMServer doesn't accept an admin DSN. You can set the porat with msyql_ram_server_port.");
  }

  public function setMySQLRamServerPort($port) {
    $this->port = $port;
    $this->adminDatasource = new Datasource(array('civi_dsn' => "mysql://root:{$this->mysql_admin_password}@127.0.0.1:{$this->port}/"));
  }

  /**
   * @param \Amp\RamDisk\RamDiskInterface $ram_disk
   * @void
   */
  public function setRamDisk($ram_disk) {
    $this->ram_disk = $ram_disk;

    Path::mkdir_p_if_not_exists($ram_disk->getPath());
    $this->mysql_data_path = Path::join($ram_disk->getPath(), 'mysql');
    $this->tmp_path = Path::join($ram_disk->getPath(), 'tmp');
    $this->mysqld_pid_file_path = Path::join($this->tmp_path, 'mysqld.pid');
    $this->mysql_socket_path = Path::join($this->tmp_path, 'mysqld.sock');
  }

  /**
   * @param \Amp\Database\MySQLRAMServer\AppArmor $app_armor
   */
  public function setAppArmor($app_armor) {
    $this->app_armor = $app_armor;
  }
}
