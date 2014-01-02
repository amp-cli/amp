<?php
namespace Amp\Database;
use Amp\Database\Datasource;

class MySQL implements DatabaseManagementInterface {
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
   */
  public function createDatabase(Datasource $datasource) {
    $db = $datasource->getDatabase();
    $user = $datasource->getUsername();
    $pass = $datasource->getPassword();

    $dbh = $this->adminDatasource->createPDO();
    $dbh->exec("CREATE DATABASE `$db`");
    $dbh->exec("GRANT ALL ON `$db`.* to '$user'@'localhost' IDENTIFIED BY '$pass'");
    $dbh->exec("GRANT ALL ON `$db`.* to '$user'@'%' IDENTIFIED BY '$pass'");
  }

  /**
   * Create a database and grant access to a (new) user
   *
   * @param Datasource $datasource
   */
  public function dropDatabase($datasource) {
    $dbh = $this->adminDatasource->createPDO();
    $dbh->exec("DROP DATABASE IF EXISTS `{$datasource->getDatabase()}`");
  }
}
