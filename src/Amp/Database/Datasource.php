<?php
namespace Amp\Database;
use Amp\Exception\InvalidSettingException;

class Datasource {
  private static $attribute_names = array(
    'database',
    'driver',
    'host',
    'password',
    'port',
    'username',
  );
  private static $cividsn_to_settings_name = array(
    'database' => 'database',
    'dbsyntax' => 'driver',
    'hostspec' => 'host',
    'password' => 'password',
    'port' => 'port',
    'username' => 'username',
  );
  private static $settings_to_doctrine_options = array(
    'database' => 'dbname',
    'driver' => 'driver',
    'host' => 'host',
    'password' => 'password',
    'port' => 'port',
    'username' => 'user',
  );
  private static $settings_to_pdo_options = array(
    'host' => 'host',
    'port' => 'port',
    'database' => 'dbname',
    'socket_path' => 'unix_socket',
  );

  private $database;
  private $driver;
  private $host;
  private $password;
  private $port;
  private $socket_path;
  private $username;

  function __construct($options = NULL) {
    if ($options !== NULL) {
      if (isset($options['civi_dsn'])) {
        $this->loadFromCiviDSN($options['civi_dsn']);
      }
      elseif ($options['settings_array']) {
        $this->loadFromSettingsArray($options['settings_array']);
      }
      else {
        var_dump(array('o' => $options));
        throw new InvalidSettingException("The options parameter needs to be blank if you want to load from CIVICRM_DSN, or it can be an array with key 'civi_dsn' that is a CiviCRM formatted DSN string, or it can be an array with key 'settings_array' than points to another array of database settings.");
      }
    }
  }

  function loadFromCiviDSN($civi_dsn) {
    require_once("DB.php");
    $db = new \DB();
    $parsed_dsn = $db->parseDSN($civi_dsn);
    foreach (static::$cividsn_to_settings_name as $key => $value) {
      if (array_key_exists($key, $parsed_dsn)) {
        $this->$value = $parsed_dsn[$key];
      }
    }
    $this->updateHost();
  }

  function loadFromSettingsArray($settings_array) {
    foreach ($settings_array as $key => $value) {
      $this->$key = $value;
    }
    $this->updateHost();
  }

  /**
   * @return PDO
   */
  function createPDO() {
    return new \PDO($this->toPDODSN(), $this->username, $this->password);
  }

  function toCiviDSN() {
    $civi_dsn = "mysql://{$this->username}:{$this->password}@{$this->host}";
    if ($this->port !== NULL) {
      $civi_dsn = "$civi_dsn:{$this->port}";
    }
    $civi_dsn = "$civi_dsn/{$this->database}?new_link=true";
    return $civi_dsn;
  }

  function toDoctrineArray() {
    $result = array();
    foreach (self::$settings_to_doctrine_options as $key => $value) {
      $result[$value] = $this->$key;
    }
    $result['driver'] = "pdo_{$result['driver']}";
    return $result;
  }

  function toDrupalDSN() {
    $drupal_dsn = "{$this->driver}://{$this->username}:{$this->password}@{$this->host}";
    if ($this->port !== NULL) {
      $drupal_dsn = "$drupal_dsn:{$this->port}";
    }
    $drupal_dsn = "$drupal_dsn/{$this->database}";
    return $drupal_dsn;
  }

  function toMySQLArguments() {
    $args = "-h {$this->host} -u {$this->username} -p{$this->password}";
    if ($this->port != NULL) {
      $args .= " -P {$this->port}";
    }
    $args .= " {$this->database}";
    return $args;
  }

  function toPHPArrayString() {
    $result = "array(\n";
    foreach (static::$attribute_names as $attribute_name) {
      $result .= "  '$attribute_name' => '{$this->$attribute_name}',\n";
    }
    $result .= ")";
    return $result;
  }

  function toPDODSN($options = array()) {
    $pdo_dsn = "{$this->driver}:";
    $pdo_dsn_options = array();
    $settings_to_pdo_options = static::$settings_to_pdo_options;
    if (isset($options['no_database']) && $options['no_database']) {
      unset($settings_to_pdo_options['database']);
    }
    foreach ($settings_to_pdo_options as $settings_name => $pdo_name) {
      if ($this->$settings_name !== NULL) {
        $pdo_dsn_options[] = "{$pdo_name}={$this->$settings_name}";
      }
    }
    $pdo_dsn .= implode(';', $pdo_dsn_options);
    return $pdo_dsn;
  }

  function updateHost() {
    /*
     * If you use localhost for the host, the MySQL client library will
     * use a unix socket to connect to the server and ignore the port,
     * so if someone is not going to use the default port, let's
     * assume they don't want to use the unix socket.
     */
    if ($this->port != NULL && $this->host == 'localhost') {
      $this->host = '127.0.0.1';
    }
  }

  public function setDatabase($database) {
    $this->database = $database;
  }

  public function getDatabase() {
    return $this->database;
  }

  public function setDriver($driver) {
    $this->driver = $driver;
  }

  public function getDriver() {
    return $this->driver;
  }

  public function setHost($host) {
    $this->host = $host;
  }

  public function getHost() {
    return $this->host;
  }

  public function setPassword($password) {
    $this->password = $password;
  }

  public function getPassword() {
    return $this->password;
  }

  public function setPort($port) {
    $this->port = $port;
  }

  public function getPort() {
    return $this->port;
  }

  public function setUsername($username) {
    $this->username = $username;
  }

  public function getUsername() {
    return $this->username;
  }

  public function setSocketPath($socket_path) {
    $this->socket_path = $socket_path;
  }

  public function getSocketPath() {
    return $this->socket_path;
  }

}
