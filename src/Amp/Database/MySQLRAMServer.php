<?php
namespace Amp\Database;

use Amp\Database\Datasource;
use Amp\Database\MySQL;
use Amp\Util\Path;

class MySQLRAMServer extends MySQL {
  public $mysqld_command = 'mysqld';
  public $mysqld_pid_file_path;
  public $mysql_data_path;
  public $mysql_admin_user = 'root';
  public $mysql_admin_password = 'root';
  public $mysql_socket_path;
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

  /**
   * @var array list of SQL files to load into the new database
   */
  public $default_data_files;

  public function init() {
    if (!$this->ram_disk->isMounted()) {
      $this->ram_disk->mount();
    }
    Path::mkdir_p_if_not_exists(Path::join($this->mysql_data_path));
    Path::mkdir_p_if_not_exists($this->tmp_path);
    if ($this->app_armor) {
      $this->app_armor->setTmpPath($this->tmp_path);  // TODO: move to services.yml or remove entirely
      if (!$this->app_armor->isConfigured()) {
        $this->app_armor->configure();
      }
    }

    if (!$this->isRunning()) {
      $this->initializeDatabase();
      $this->runCommand("{$this->getMySQLDBaseCommand()} > {$this->tmp_path}/mysql-drupal-test.log 2>&1 &");
      $i = 0;
      if (!file_exists($this->mysql_socket_path) or $i > 9) {
        $i++;
        sleep(1);
      }
      if ($i == 9) {
        throw new \Exception("There was a problem starting the MySQLRAM server. We expect to see a socket file at {$this->mysql_socket_path} but it hasn't appeared after 10 waiting seconds.");
      }

      // Probably new DB files
      $i = 0;
      $last_exception = NULL;
      while ($i < 9) {
        $i++;
        try {
          $this->runCommand("mysqladmin --socket={$this->mysql_socket_path} --user=root --password='' password '{$this->mysql_admin_password}'");
          break;
        }
        catch (\Exception $e) {
          if ($this->adminDatasource->isValid()) {
            break; // may happen if user killed mysqld without resetting ramdisk
          }
          $last_exception = $e;
        }
        sleep(1);
      }
      if ($i == 9) {
        throw $last_exception;
      }
    }
  }

  public function createDatasource($hint) {
    if (!$this->isRunning()) {
      $this->init();
    }
    $pass = \Amp\Util\StringUtil::createRandom(16);
    $user = \Amp\Util\StringUtil::createHintedRandom($hint, 16, 5, 'abcdefghijklmnopqrstuvwxyz0123456789');

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

  public function createDatabase(Datasource $datasource, $perm = DatabaseManagementInterface::PERM_ADMIN) {
    if (!$this->isRunning()) {
      $this->init();
    }
    parent::createDatabase($datasource, $perm);
  }

  public function dropDatabase($datasource) {
    if (!$this->isRunning()) {
      $this->init();
    }
    parent::dropDatabase($datasource);
  }

  public function isRunning() {
    $port_checker = new \Amp\Util\PortChecker();
    return $port_checker->checkHostPort('localhost', $this->port);
  }

  public function runCommand($command, $options = array()) {
    // $options['print_command'] = TRUE;
    return \Amp\Util\Shell::run($command, $options);
  }

  public function setAdminDsn($dns) {
    throw new \Exception("MySQLinuxRAMServer doesn't accept an admin DSN. You can set the porat with msyql_ram_server_port.");
  }

  public function setMySQLRamServerPort($port) {
    $this->port = $port;
    $this->adminDatasource = new Datasource(array('civi_dsn' => "mysqli://{$this->mysql_admin_user}:{$this->mysql_admin_password}@127.0.0.1:{$this->port}/"));
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

  /**
   * @param array|\ArrayObject $default_data_files list of SQL files to load into the new database
   */
  public function setDefaultDataFiles($default_data_files) {
    $this->default_data_files = $default_data_files;
  }

  /**
   * @return array|\ArrayObject list of SQL files to load into the new database
   */
  public function getDefaultDataFiles() {
    return $this->default_data_files;
  }

  public function getMySQLDBaseCommand() {
    return "{$this->mysqld_command} --no-defaults --tmpdir={$this->tmp_path} --datadir={$this->mysql_data_path} --port={$this->port} --socket={$this->mysql_socket_path} --pid-file={$this->mysqld_pid_file_path} --innodb-file-per-table";
  }

  protected function getVersion() {
    $output = `{$this->mysqld_command}  --version`;
    if (preg_match(';mysqld\s+Ver ([0-9][0-9\.+\-a-zA-Z]*)\s;', $output, $matches)) {
      return $matches[1];
    }
    else {
      throw new \RuntimeException("Failed to determine mysqld version. (\"$output\")");
    }
  }

  /**
   * @param bool $force
   *   Initialize database files, even files exist.
   * @throws \Exception
   */
  protected function initializeDatabase($force = FALSE) {
    // FIXME: This should probably be rewritten as a wrapper for mysql_install_db
    // with options communicated by creating a custom ~/.amp/ram_disk/tmp/my.cnf

    if (!$force && glob("{$this->mysql_data_path}/*")) {
      return;
    }

    $mysqldVersion = $this->getVersion();

    $options = '';
    $pipe = '';

    if (version_compare($mysqldVersion, '5.7.6', '<=')) {
      Path::mkdir_p_if_not_exists(Path::join($this->mysql_data_path, 'mysql'));
      $this->runCommand("echo \"use mysql;\" > {$this->tmp_path}/install_mysql.sql");
      if ($this->getDefaultDataFiles()) {
        $this->runCommand("cat " . implode(' ', array_map('escapeshellarg', (array) $this->getDefaultDataFiles())) . " >> {$this->tmp_path}/install_mysql.sql");
      }
      else {
        throw new \Exception("Error finding default data files");
      }
      $pipe = "< {$this->tmp_path}/install_mysql.sql";
      $options .= ' --bootstrap ';
    }
    else {
      $options .= ' --initialize-insecure';
    }

    $options .= version_compare($mysqldVersion, '5.7.2', '<=') ? ' --log-warnings=0' : ' --log-error-verbosity=1';
    $options .= ' --innodb';
    $options .= ' --default-storage-engine=innodb';
    $options .= ' --max_allowed_packet=8M';
    $options .= ' --net_buffer_length=16K';

    $this->runCommand("{$this->getMySQLDBaseCommand()} $options $pipe");
  }

}
