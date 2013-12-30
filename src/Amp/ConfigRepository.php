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
      'httpd_type' => 'Type of webserver [apache,nginx]',
      'apache_dir' => 'Directory which stores Apache config files',
      'apache_tpl' => 'Apache configuration template',
      'nginx_dir' => 'Directory which stores nginx config files',
      'nginx_tpl' => 'Nginx configuration template',
      'mysql_type' => 'How to connect to MySQL admin [cli,dsn,linuxRamDisk]',
      'mysql_dsn' => 'Administrative credentials for MySQL',
      'perm_type' => 'How to set permissions on data directories [none,custom,linuxAcl,osxAcl,worldWritable]',
      'perm_user' => 'Name of the web user [for linuxAcl,osxAcl]',
      'perm_custom_command' => 'Command to set a directory as web-writeable [for custom]',
    );

    // FIXME externalize
    // Each callback returns a scalar or array of examples
    $this->exampleCallbacks = array(
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
      'perm_user' => function () {
        $webUsers = \Amp\Util\User::filterValidUsers(array(
          'www-data',
          'www',
          '_www',
          'apache',
          'apache2',
          'nginx',
          'httpd'
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
    );
  }

  protected function load() {
    if ($this->data === NULL) {
      if (file_exists($this->getFile())) {
        $this->data = Yaml::parse(file_get_contents($this->getFile()));
      }
      else {
        $this->data = array(
          'parameters' => array(),
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