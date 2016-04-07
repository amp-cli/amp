<?php
namespace Amp;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ConfigRepository {

  /**
   * @param mixed $data
   */
  var $data;

  /**
   * @var array
   */
  var $descriptions;

  /**
   * @var array (string $parameter => callable $callback)
   */
  var $exampleCallbacks;

  /**
   * @var string
   */
  var $file;

  /**
   * @var null|int $fileMode
   */
  private $fileMode = 0640;

  /**
   * @var FileSystem
   */
  private $fs = NULL;

  public function __construct() {
    $this->fs = new Filesystem();

    // FIXME externalize
    $this->descriptions = array(
      'httpd_type' => 'Type of webserver [none,apache,apache24,nginx]',
      'httpd_restart_command' => 'Command to restart httpd (ex: sudo apache2ctl graceful)',
      'hosts_type' => 'Type of hostname management [none,file]',
      'hosts_file' => 'Location of the hosts file (ex: /etc/hosts)',
      'hosts_ip' => 'Default IP for new entries in hosts file (ex: 127.0.0.1)',
      'log_dir' => 'Directory which stores log files',
      'apache_dir' => 'Directory which stores Apache config files',
      //'apache24_dir' => 'Directory which stores Apache config files',
      'apache_tpl' => 'Apache configuration template',
      'apache24_tpl' => 'Apache 2.4 or greater configuration template',
      'nginx_dir' => 'Directory which stores nginx config files',
      'nginx_tpl' => 'Nginx configuration template',
      'db_type' => 'How to connect to the database as admin [mysql_dsn,mysql_mycnf,mysql_ram_disk,mysql_osx_ram_disk,pg_dsn]',
      'mysql_dsn' => 'Administrative credentials for MySQL',
      'pg_dsn' => 'Administrative credentials for PostgreSQL',
      'perm_type' => "How to set permissions on data directories [none,custom,linuxAcl,osxAcl,worldWritable]. See https://github.com/totten/amp/blob/master/doc/perm.md",
      'perm_user' => 'Name of the web user [for linuxAcl,osxAcl]',
      'perm_custom_command' => 'Command to set a directory as web-writeable [for custom]',
      'ram_disk_dir' => 'Directory to create as a RAM disk',
      'ram_disk_size' => 'Amount of space to allocate for ramdisk (MB)',
      'ram_disk_type' => 'Type of RAM disk [auto,linux,osx,manual]',
    );

    // FIXME externalize
    // Each callback returns a scalar or array of examples
    $this->exampleCallbacks = array(
      'hosts_file' => function () {
        return array('/etc/hosts');
      },
      'hosts_ip' => function () {
        return array('127.0.0.1');
      },
      'mysql_dsn' => function () {
        $checker = new \Amp\Util\PortChecker();
        // Some folks report problems using "localhost"
        $dsns = $checker->filterUrls(array(
          'mysql://user:pass@127.0.0.1:3306', // Standard port
          'mysql://user:pass@127.0.0.1:3307',
          'mysql://user:pass@127.0.0.1:8889', // MAMP port
          'mysql://user:pass@localhost:3306', // Standard port
          'mysql://user:pass@localhost:3307',
          'mysql://user:pass@localhost:8889', // MAMP port
        ));
        if (empty($dsns)) {
          return 'mysql://user:pass@hostname:3306';
        }
        else {
          return $dsns;
        }
      },
      'pg_dsn' => function () {
        $checker = new \Amp\Util\PortChecker();
        // Some folks report problems using "localhost"
        $dsns = $checker->filterUrls(array(
          'pgsql://user:pass@127.0.0.1:5432/template1',
          'pgsql://user:pass@localhost:5432/template1',
        ));
        if (empty($dsns)) {
          return 'pgsql://user:pass@hostname:5432/template1';
        }
        else {
          return $dsns;
        }
      },
      'perm_user' => function () {
        $webUsers = \Amp\Util\User::filterValidUsers(array(
          'www-data',
          'www',
          '_www',
          'apache',
          'apache2',
          'nginx',
          'httpd',
        ));
        if (empty($webUsers)) {
          return 'www-data';
        }
        else {
          return implode(', ', $webUsers);
        }
      },
      'perm_custom_command' => function () {
        $examples = array();
        $examples[] = 'chmod 1777 {DIR}';
        if (preg_match('/Linux/', php_uname()) && FALSE !== \Amp\Util\Process::findExecutable('setfacl')) {
          $examples[] = 'setfacl -m u:www-data:rwx -m d:u:www-data:rwx {DIR}';
        }
        if (preg_match('/Darwin/', php_uname())) {
          $examples[] = '/bin/chmod +a "www allow delete,write,append,file_inherit,directory_inherit" {DIR}';
        }
        return count($examples) > 1 ? $examples : $examples[0];
      },
      'httpd_restart_command' => function () {
        $APACHECTLS = array('apachectl', 'apache2ctl');
        $SYSDIRS = array(
          '/bin',
          '/sbin',
          '/usr/bin',
          '/usr/sbin',
          '/usr/local/bin',
          '/usr/local/sbin',
        );
        $examples = array();

        // Add-on kits. No need for sudo
        foreach (array('/Applications/MAMP/Library/bin') as $dir) {
          foreach ($APACHECTLS as $prog) {
            if (file_exists("$dir/$prog")) {
              $examples[] = "$dir/$prog graceful";
            }
          }
        }

        // OS distributions. Require sudo.
        foreach ($SYSDIRS as $dir) {
          foreach ($APACHECTLS as $prog) {
            if (file_exists("$dir/$prog")) {
              $examples[] = "sudo $dir/$prog graceful";
            }
          }
          if (file_exists("$dir/service")) {
            $examples[] = "sudo $dir/service apache2 restart";
            $examples[] = "sudo $dir/service httpd restart";
          }
        }

        if (empty($examples)) {
          $examples[] = "sudo apachectl graceful";
        }

        $examples[] = "NONE";

        return $examples;
      },
    );
  }

  protected function load() {
    if ($this->data === NULL) {
      if (file_exists($this->getFile())) {
        $this->data = Yaml::parse(file_get_contents($this->getFile()));
      }
      else {
        $this->data = array(
          'parameters' => array(
            'version' => 'new',
          ),
          'services' => array(),
        );
      }
    }
  }

  public function getParameter($key) {
    $this->load();
    return isset($this->data['parameters'][$key]) ? $this->data['parameters'][$key] : NULL;
  }

  public function setParameter($key, $value) {
    $this->load();
    $this->data['parameters'][$key] = $value;
  }

  public function unsetParameter($key) {
    $this->load();
    unset($this->data['parameters'][$key]);
  }

  public function save() {
    $this->load();
    $this->fs->dumpFile($this->getFile(), Yaml::dump($this->data), $this->getFileMode());
  }

  /**
   * @param string $file
   */
  public function setFile($file) {
    $this->file = $file;
  }

  /**
   * @return string
   */
  public function getFile() {
    return $this->file;
  }

  /**
   * @param int|null $fileMode
   */
  public function setFileMode($fileMode) {
    $this->fileMode = $fileMode;
  }

  /**
   * @return int|null
   */
  public function getFileMode() {
    return $this->fileMode;
  }

  /**
   * @return array of parameter names (strings)
   */
  public function getParameters() {
    return array_keys($this->descriptions);
  }

  /**
   * @return string|NULL
   */
  public function getDescription($parameter) {
    return isset($this->descriptions[$parameter]) ? $this->descriptions[$parameter] : NULL;
  }

  /**
   * @return string|NULL
   */
  public function getExample($parameter) {
    if (isset($this->exampleCallbacks[$parameter])) {
      return call_user_func($this->exampleCallbacks[$parameter]);
    }
    else {
      return NULL;
    }
  }

}
