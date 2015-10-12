<?php
namespace Amp\Database;
use Amp\Database\DatabaseManagementInterface;
use Amp\Database\Datasource;

class PostgreSQL implements DatabaseManagementInterface {
  /**
   * @var Datasource
   */
  protected $adminDatasource = NULL;

  /**
   * @return bool
   */
  public function isRunning() {
    return TRUE; // FIXME
  }

  /**
   * @param string $dsn
   */
  public function setAdminDsn($dsn) {
    if ($dsn) {
      $this->adminDatasource = new Datasource(array(
        'civi_dsn' => $dsn,
      ));
    }
    else {
      $this->adminDatasource = NULL;
    }
  }

  /**
   * @param \Amp\Database\Datasource $adminDatasource
   */
  public function setAdminDatasource($adminDatasource) {
    $this->adminDatasource = $adminDatasource;
  }

  /**
   * @return \Amp\Database\Datasource
   */
  public function getAdminDatasource() {
    return $this->adminDatasource;
  }

  /**
   * Create a datasource representing a new user and database
   *
   * @param string $hint an advisory string; ideally included in $db/$user
   * @return Datasource;
   */
  public function createDatasource($hint) {
    $pass = \Amp\Util\String::createRandom(16);
    $user = \Amp\Util\String::createHintedRandom($hint, 16, 5, 'abcdefghijklmnopqrstuvwxyz0123456789');

    $datasource = new Datasource();
    $datasource->setDriver($this->adminDatasource->getDriver());
    $datasource->setHost($this->adminDatasource->getHost());
    $datasource->setPort($this->adminDatasource->getPort());
    $datasource->setSocketPath($this->adminDatasource->getSocketPath());
    $datasource->setUsername($user);
    $datasource->setPassword($pass);
    $datasource->setDatabase($user);

    return $datasource;
  }

  /**
   * Create a database and grant access to a (new) user
   *
   * @param Datasource $datasource
   * @param string $perm is not used
   */
  public function createDatabase(Datasource $datasource, $perm = DatabaseManagementInterface::PERM_ADMIN) {
    $db = $datasource->getDatabase();
    $user = $datasource->getUsername();
    $pass = $datasource->getPassword();

    $dbh = $this->adminDatasource->createPDO();
    
    $dbh->exec("DROP DATABASE IF EXISTS  \"$db\"");
    $dbh->exec("DROP ROLE IF EXISTS  $user");

    $dbh->exec("CREATE ROLE $user with encrypted password '$pass' login");
    $dbh->exec("CREATE DATABASE \"$db\" owner $user");
  }

  /**
   * Create a database and grant access to a (new) user
   *
   * @param Datasource $datasource
   */
  public function dropDatabase($datasource) {
    $dbh = $this->adminDatasource->createPDO();
    $dbh->exec("DROP DATABASE IF EXISTS \"{$datasource->getDatabase()}\"");
  }
}
