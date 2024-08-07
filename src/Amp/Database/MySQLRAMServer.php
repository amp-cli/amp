<?php
namespace Amp\Database;

use Amp\Util\Path;
use Amp\Util\Version;

class MySQLRAMServer extends MySQL {

  /**
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
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
   * List of SQL files to load into the new database
   *
   * @var array
   */
  public $default_data_files;

  public function init() {
    if (!$this->ram_disk->isMounted()) {
      $this->ram_disk->mount();
    }
    Path::mkdir_p_if_not_exists(Path::join($this->mysqld_data_path));
    Path::mkdir_p_if_not_exists($this->mysqld_tmp_path);
    if ($this->app_armor) {
      // TODO: move to services.yml or remove entirely
      $this->app_armor->setTmpPath($this->mysqld_tmp_path);
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
            // may happen if user killed mysqld without resetting ramdisk
            break;
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
    $db = \Amp\Util\StringUtil::createHintedRandom($hint, 32, 5, 'abcdefghijklmnopqrstuvwxyz0123456789');

    $datasource = new Datasource();
    $datasource->setDriver($this->adminDatasource->getDriver());
    $datasource->setHost('127.0.0.1');
    $datasource->setPort($this->mysqld_port);
    $datasource->setSocketPath($this->mysqld_socket_path);
    $datasource->setUsername($user);
    $datasource->setPassword($pass);
    $datasource->setDatabase($db);

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

  public function getMySQLDBaseCommand($cmd) {
    $mysqldVersion = $this->getVersion();
    $isMariaDB = version_compare($mysqldVersion, '10.0', '>=');

    $parts = [];

    $parts[] = "--tmpdir=" . escapeshellarg($this->mysqld_tmp_path);
    $parts[] = "--datadir=" . escapeshellarg($this->mysqld_data_path);
    $parts[] = "--port=" . escapeshellarg($this->mysqld_port);
    $parts[] = "--socket=" . escapeshellarg($this->mysqld_socket_path);
    $parts[] = "--pid-file=" . escapeshellarg($this->mysqld_pid_path);
    if (version_compare($mysqldVersion, '8.0', '<')) {
      $parts[] = ' --innodb-file-format=Barracuda';
      $parts[] = ' --innodb-file-per-table';
    }

    // Enable innodb-large-prefix on MySQL 5.6 versions.
    if (version_compare($mysqldVersion, '5.7', '<') && version_compare($mysqldVersion, '5.6', '>=')) {
      $parts[] = ' --innodb-large-prefix=TRUE';
    }

    $uname = function_exists('posix_uname') ? posix_uname() : NULL;
    if ($uname && $uname['sysname'] === 'Darwin') {
      // Mitigation for "File Descriptor n exceedeed FD_SETSIZE" when using several large builds
      // https://gist.github.com/bbrown/73d6975ac7324141dd934d325f7cd358
      // https://bugs.mysql.com/bug.php?id=79125
      $parts[] = "--table-open-cache=250";
      $parts[] = "--max-allowed-packet=256M";
    }

    // In MySQL 8 Binary logging is turned on bydefault
    if (version_compare($mysqldVersion, '8.0', '>=') && version_compare($mysqldVersion, '10.0', '<')) {
      $parts[] = '--disable-log-bin';
    }

    if ($isMariaDB) {
      // skip auth-plugin options
    }
    elseif (Version::compare('8.0', '<=', $mysqldVersion, '<', '8.4')) {
      // Allow mysql clients running PHP 7.1-7.3 to connect as root (et al)
      $parts[] = '--default-authentication-plugin=mysql_native_password';
    }
    elseif (Version::compare('8.4', '<=', $mysqldVersion, '<', '9')) {
      // Allow mysql clients running PHP 7.1-7.3 to connect as root (et al)
      $parts[] = '--mysql-native-password=on --authentication-policy=mysql_native_password';
    }

    return "$cmd --no-defaults " . implode(' ', $parts);
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
    $isMariaDB = version_compare($mysqldVersion, '10.0', '>=');

    $options = '';
    $pipe = '';

    if ($isMariaDB) {
      Path::mkdir_p_if_not_exists(Path::join($this->mysqld_data_path, 'mysql'));
      $escapeMysqldPath = escapeshellarg($this->mysqld_bin);
      $fullMysqldPath = trim(`which $escapeMysqldPath`);
      $binDir = dirname($fullMysqldPath);
      $baseCmd = sprintf('cd %s && ', escapeshellarg(dirname($binDir))) . $this->getMySQLDBaseCommand('./bin/mysql_install_db');
      $options .= ' --skip-name-resolve';
    }
    elseif (version_compare($mysqldVersion, '5.7.6', '<=')) {
      Path::mkdir_p_if_not_exists(Path::join($this->mysqld_data_path, 'mysql'));
      $this->runCommand("echo \"use mysql;\" > {$this->mysqld_tmp_path}/install_mysql.sql");
      if ($this->getDefaultDataFiles()) {
        $this->runCommand("cat " . implode(' ', array_map('escapeshellarg', (array) $this->getDefaultDataFiles())) . " >> {$this->mysqld_tmp_path}/install_mysql.sql");
      }
      else {
        throw new \Exception("Error finding default data files");
      }

      $baseCmd = $this->getMySQLDBaseCommand($this->mysqld_bin);
      $pipe = "< {$this->mysqld_tmp_path}/install_mysql.sql";
      $options .= ' --bootstrap ';
    }
    else {
      $baseCmd = $this->getMySQLDBaseCommand($this->mysqld_bin);
      $options .= ' --initialize-insecure';
    }

    $options .= (version_compare($mysqldVersion, '5.7.2', '<=') || $isMariaDB) ? ' --log-warnings=0' : ' --log-error-verbosity=1';
    if ($isMariaDB || version_compare($mysqldVersion, '8.4', '<')) {
      $options .= ' --innodb';
    }
    $options .= ' --default-storage-engine=innodb';
    $options .= ' --max_allowed_packet=8M';
    $options .= ' --net_buffer_length=16K';

    // printf("mysql version [%s] isMaria=[%s] mysqldbin=[%s]\ninit cmd=[%s]\n", $mysqldVersion, $isMariaDB ? 'y':'n', $this->mysqld_bin, "$baseCmd $options $pipe");
    return "$baseCmd $options $pipe";
  }

  /**
   * @return string
   */
  protected function createLaunchCommand() {
    return $this->getMySQLDBaseCommand($this->mysqld_bin) . " > {$this->mysqld_tmp_path}/mysql-drupal-test.log 2>&1 &";
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
