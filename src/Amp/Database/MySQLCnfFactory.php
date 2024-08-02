<?php
namespace Amp\Database;

class MySQLCnfFactory {

  public static function get() {
    $factory = new self(
      array(getenv('HOME') . '/.my.cnf'),
      array('127.0.0.1', 'localhost'),
      array(3306, 3307, 8889)
    );

    $datasource = $factory->createDatasource();
    if (!$datasource) {
      throw new \RuntimeException("Failed to locate MySQL service via ~/.my.cnf");
    }

    $mysql = new \Amp\Database\MySQL();
    $mysql->setAdminDatasource($datasource);
    return $mysql;
  }

  /**
   * @var string[]
   */
  private $iniPaths;

  /**
   * @var string[]
   */
  private $defaultHostnames;

  /**
   * @var int[]
   */
  private $defaultPorts;

  public function __construct($iniPaths, $hostnames, $ports) {
    $this->iniPaths = $iniPaths;
    $this->defaultHostnames = $hostnames;
    $this->defaultPorts = $ports;
  }

  /**
   * Create a valid datasource by scanning all
   * the candidates.
   *
   * @return Datasource|null
   */
  public function createDatasource() {
    $datasources = $this->createCandidateDatasources();
    foreach ($datasources as $datasource) {
      /** @var Datasource $datasource */
      if ($datasource->isValid()) {
        return $datasource;
      }
    }
    return NULL;
  }

  /**
   * Create a list of possible DSNs
   * @return array of Datasource
   */
  public function createCandidateDatasources() {
    $datasources = array();
    foreach ($this->iniPaths as $iniPath) {
      if (file_exists($iniPath)) {
        $datasources = array_merge($datasources, $this->parseDatasources(file_get_contents($iniPath)));
      }
    }
    return $datasources;
  }

  /**
   * Get a list of possible username/password pairs
   *
   * @param $iniString
   * @return array
   */
  public function parseDatasources($iniString) {
    $datasources = array();

    $ini = parse_ini_string($iniString, TRUE);
    foreach (array('client', 'mysqladmin', 'mysql') as $section) {
      if (isset($ini[$section], $ini[$section]['user'])) {
        $hosts = isset($ini[$section]['host']) ? array($ini[$section]['host']) : $this->defaultHostnames;
        $ports = isset($ini[$section]['port']) ? array($ini[$section]['port']) : $this->defaultPorts;
        foreach ($hosts as $host) {
          foreach ($ports as $port) {
            $key = implode('-', array($host, $port, $ini[$section]['user'], @$ini[$section]['password']));
            $datasources[$key] = new Datasource(array(
              'settings_array' => array(
                'driver' => 'mysql',
                'host' => $host,
                'port' => $port,
                'username' => $ini[$section]['user'],
                'password' => @$ini[$section]['password'],
              ),
            ));
          }
        }
      }
    }

    return $datasources;
  }

  /*
  public static function pollPortViaCLI($command = 'echo "SHOW VARIABLES WHERE Variable_name = \'port\'" | mysql') {
  $p = new Process($command);
  $p->run();
  if (!$p->isSuccessful()) {
  throw new ProcessException($p, "Bad exit status");
  }
  $lines = explode("\n", trim($p->getOutput()));
  if (count($lines) != 2) {
  throw new ProcessException($p, "Wrong line count");
  }
  $parts = explode("\t", $lines[1]);
  if (count($parts) != 2 || $parts[0] != 'port' || !is_numeric($parts[1])) {
  throw new ProcessException($p, "Wrong column count");
  }
  return (int) $parts[1];
  }
   */
}
