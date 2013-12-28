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
   * @var array
   */
  var $examples;

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
    $this->examples = array(
      'mysql_dsn' => 'mysql://user:pass@hostname',
      'perm_user' => 'www-data',
      'perm_custom_command' => 'chmod 1777 {DIR}',
    );

    $webUsers = \Amp\Util\User::filterValidUsers(array(
      'www-data',
      'www',
      '_www',
      'apache',
      'apache2',
      'nginx',
      'httpd'
    ));
    if ($webUsers) {
      $this->examples['perm_user'] = implode(', ', $webUsers);
    }
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
    return isset($this->examples[$parameter]) ? $this->examples[$parameter] : NULL;
  }

}