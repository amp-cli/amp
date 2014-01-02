<?php
namespace Amp\Database;

use Amp\Database\Datasource;
use Amp\Database\MySQL;
use Amp\Util\Path;
use Amp\Util\FileExt;

class MySQLRAMServer extends MySQL {
  public $app_armor_config_file_path = "/etc/apparmor.d/local/usr.sbin.mysqld";
  public $app_armor_lines;
  public $mysqld_base_command;
  public $mysql_data_path;
  public $mysql_admin_user = 'root';
  public $mysql_admin_password = 'root';
  public $port;
  public $ram_disk_path;

  public function appArmorConfigured() {
    $this->buildAppArmorLines();
    if (!file_exists($this->app_armor_config_file_path)) {
      return FALSE;
    }
    $app_armor_lines_flipped = array_flip($this->app_armor_lines);
    $num_matched = 0;
    $app_armor_config_file = FileExt::open($this->app_armor_config_file_path, 'r');
    while (($line = fgets($app_armor_config_file)) !== FALSE) {
      if (array_key_exists($line, $app_armor_lines_flipped)) {
        $num_matched += 1;
      }
    }
    FileExt::close($app_armor_config_file);
    if ($num_matched == count($this->app_armor_lines)) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  public function buildAppArmorLines() {
    if ($this->app_armor_lines == NULL) {
      $this->app_armor_lines = array(
        "{$this->ram_disk_path}/ r,\n",
        "{$this->ram_disk_path}/** rwk,\n",
      );
    }
  }

  public function buildMySQLDBaseCommand() {
    $this->mysqld_base_command = "mysqld --no-defaults --tmpdir={$this->tmp_path} --datadir={$this->mysql_data_path} --port={$this->port} --socket={$this->mysql_socket_path} --pid-file={$this->mysqld_pid_file_path}";
  }

  public function configureAppArmor() {
    $this->buildAppArmorLines();
    $file_system = new \Amp\Util\Filesystem();
    $new_config_file_path = Path::join($this->tmp_path, basename($this->app_armor_config_file_path));
    $file_system->copy($this->app_armor_config_file_path, $new_config_file_path);
    $new_config_file = FileExt::open($new_config_file_path, 'a');
    FileExt::write($new_config_file, "\n");
    foreach ($this->app_armor_lines as $app_armor_line) {
      FileExt::write($new_config_file, $app_armor_line);
    }
    FileExt::close($new_config_file);
    $this->runCommand("sudo mv $new_config_file_path {$this->app_armor_config_file_path}");
    $this->runCommand("sudo /etc/init.d/apparmor restart", array('throw_exception_on_nonzero' => FALSE));
  }


  public function createDatasource($hint) {
    if (!$this->ramDiskIsMounted()) {
      $this->mountRAMDisk();
    }
    if (!$this->appArmorConfigured()) {
      $this->configureAppArmor();
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

  public function mountRAMDisk() {
    $this->runCommand("sudo mount -t tmpfs -o size=500m tmpfs {$this->ram_disk_path}");
    $uid = getmyuid();
    $gid = getmygid();
    $this->runCommand("sudo chown $uid:$gid {$this->ram_disk_path}");
    $this->runCommand("chmod 0755 {$this->ram_disk_path}");
    Path::mkdir_p_if_not_exists(Path::join($this->mysql_data_path, 'mysql'));
    Path::mkdir_p_if_not_exists($this->tmp_path);
  }

  public function ramDiskIsMounted() {
    $result = $this->runCommand("stat -f -c '%T' {$this->ram_disk_path}");
    if (trim($result[0]) != 'tmpfs') {
      return FALSE;
    } else {
      return TRUE;
    }
  }

  public function runCommand($command, $options = array()) {
    $options['print_command'] = true;
    return \Amp\Util\Shell::run($command);
  }

  public function setAdminDsn($dns) {
    throw new Exception("MySQLinuxRAMServer doesn't accept an admin DSN. You can set the porat with msyql_ram_server_port.");
  }

  public function setMYSQLRamServerPort($port) {
    $this->port = $port;
    $this->adminDatasource = new Datasource(array('civi_dsn' => "mysql://root:{$this->mysql_admin_password}@127.0.0.1:{$this->port}/"));
  }

  public function setRAMDiskPath($path) {
    $this->ram_disk_path = $path;
    Path::mkdir_p_if_not_exists($this->ram_disk_path);
    $this->mysql_data_path = Path::join($this->ram_disk_path, 'mysql');
    $this->tmp_path = Path::join($this->ram_disk_path, 'tmp');
    $this->mysqld_pid_file_path = Path::join($this->tmp_path, 'mysqld.pid');
    $this->mysql_socket_path = Path::join($this->tmp_path, 'mysqld.sock');
  }
}
