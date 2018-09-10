<?php
namespace Amp\Database;

use Amp\Database\Datasource;
use Amp\Database\MySQL;
use Amp\Util\Path;
use Amp\Util\Shell;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MySQLRAMServer extends MySQL {

  /**
   * @var ContainerInterface
   */
  public $container;

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
    Path::mkdir_p_if_not_exists(Path::join($this->mysqld_data_path));
    Path::mkdir_p_if_not_exists($this->mysqld_tmp_path);
    if ($this->app_armor) {
      $this->app_armor->setTmpPath($this->mysqld_tmp_path);  // TODO: move to services.yml or remove entirely
      if (!$this->app_armor->isConfigured()) {
        $this->app_armor->configure();
      }
    }

    if (!$this->isRunning()) {
      $this->runCommand($this->createInitializationCommand());
      $this->runCommand($this->createLaunchCommand());
      $i = 0;
      if (!file_exists($this->mysqld_socket_path) or $i > 9) {
        $i++;
        sleep(1);
      }
      if ($i == 9) {
        throw new \Exception("There was a problem starting the MySQL RAM server. We expect to see a socket file at {$this->mysqld_socket_path} but it hasn't appeared after 10 waiting seconds.");
      }

      // Probably new DB files
      $i = 0;
      $last_exception = NULL;
      while ($i < 9) {
        $i++;
        try {
          $this->runCommand($this->createPasswordCommand());
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
    $datasource->setPort($this->mysqld_port);
    $datasource->setSocketPath($this->mysqld_socket_path);
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
    return $port_checker->checkHostPort('localhost', $this->mysqld_port);
  }

  public function runCommand($command, $options = array()) {
    // $options['print_command'] = TRUE;
    return ($command === NULL) ? NULL : \Amp\Util\Shell::run($command, $options);
  }

  public function setAdminDsn($dsn) {
    throw new \Exception("MySQLRAMServer doesn't accept an admin DSN. You can set the port with mysqld_port.");
  }

  public function buildAdminDatasource() {
    $this->adminDatasource = new Datasource(array('civi_dsn' => "mysql://{$this->mysqld_admin_user}:{$this->mysqld_admin_password}@127.0.0.1:{$this->mysqld_port}/"));
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
    return "{$this->mysqld_bin} --no-defaults --tmpdir={$this->mysqld_tmp_path} --datadir={$this->mysqld_data_path} --port={$this->mysqld_port} --socket={$this->mysqld_socket_path} --pid-file={$this->mysqld_pid_path} --innodb-file-per-table --innodb-file-format=Barracuda";
  }

  protected function getVersion() {
    $output = `{$this->mysqld_bin}  --version`;
    if (preg_match(';mysqld(.bin)?\s+Ver ([0-9][0-9\.+\-a-zA-Z]*)\s;', $output, $matches)) {
      return $matches[2];
    }
    else {
      throw new \RuntimeException("Failed to determine mysqld version. (\"$output\")");
    }
  }

  /**
   * @param bool $force
   *   Initialize database files, even files exist.
   * @return string|NULL
   * @throws \Exception
   */
  protected function createInitializationCommand($force = FALSE) {
    // FIXME: This should probably be rewritten as a wrapper for mysql_install_db
    // with options communicated by creating a custom ~/.amp/ram_disk/tmp/my.cnf

    if (!$force && glob("{$this->mysqld_data_path}/*")) {
      return NULL;
    }

    $mysqldVersion = $this->getVersion();

    $options = '';
    $pipe = '';

    if (version_compare($mysqldVersion, '5.7.6', '<=')) {
      Path::mkdir_p_if_not_exists(Path::join($this->mysqld_data_path, 'mysql'));
      $this->runCommand("echo \"use mysql;\" > {$this->mysqld_tmp_path}/install_mysql.sql");
      if ($this->getDefaultDataFiles()) {
        $this->runCommand("cat " . implode(' ', array_map('escapeshellarg', (array) $this->getDefaultDataFiles())) . " >> {$this->mysqld_tmp_path}/install_mysql.sql");
      }
      else {
        throw new \Exception("Error finding default data files");
      }
      $pipe = "< {$this->mysqld_tmp_path}/install_mysql.sql";
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

    return "{$this->getMySQLDBaseCommand()} $options $pipe";
  }

  /**
   * @return string
   */
  protected function createLaunchCommand() {
    return "{$this->getMySQLDBaseCommand()} > {$this->mysqld_tmp_path}/mysql-drupal-test.log 2>&1 &";
  }

  /**
   * @return string
   */
  protected function createPasswordCommand() {
    return $this->mysqladmin_bin . " --socket={$this->mysqld_socket_path} --user=root --password='' password '{$this->mysqld_admin_password}'";
    //    $data = "[client]\n";
    //    $data .= "socket={$this->mysql_socket_path}\n";
    //    $data .= "user={$this->mysqld_admin_user}\n";
    //    // $data .= "password={$this->mysqld_admin_password}\n";
    //    $data .= "password=\n";
    //
    //    $file = $this->tmp_path . '/my.cnf-' . md5($data);
    //    if (!file_exists($file)) {
    //      if (!file_put_contents($file, $data)) {
    //        throw new \RuntimeException("Failed to create temporary my.cnf connection file.");
    //      }
    //    }
    //
    //    return $this->mysqladmin_command . " --defaults-file=" . escapeshellarg($file) . " password '{$this->mysqld_admin_password}'";
  }

  public function __get($name) {
    $passthru = array('mysqladmin_bin', 'mysqld_bin', 'mysqld_port');

    if (in_array($name, $passthru)) {
      return $this->container->getParameter($name);
    }

    $evals = array('mysqld_data_path', 'mysqld_tmp_path', 'mysqld_pid_path', 'mysqld_socket_path', 'mysqld_admin_user', 'mysqld_admin_password');
    if (in_array($name, $evals)) {
      return $this->container->get('expr')->getParameter($name);
    }
  }

}
