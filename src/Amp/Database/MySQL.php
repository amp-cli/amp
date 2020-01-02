<?php
namespace Amp\Database;
use Amp\Database\DatabaseManagementInterface;
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
    $pass = \Amp\Util\StringUtil::createRandom(16);
    $user = \Amp\Util\StringUtil::createHintedRandom($hint, 16, 5, 'abcdefghijklmnopqrstuvwxyz0123456789');
    $db = \Amp\Util\StringUtil::createHintedRandom($hint, 32, 5, 'abcdefghijklmnopqrstuvwxyz0123456789');

    $datasource = new Datasource();
    $datasource->setDriver($this->adminDatasource->getDriver());
    $datasource->setHost($this->adminDatasource->getHost());
    $datasource->setPort($this->adminDatasource->getPort());
    $datasource->setSocketPath($this->adminDatasource->getSocketPath());
    $datasource->setUsername($user);
    $datasource->setPassword($pass);
    $datasource->setDatabase($db);

    return $datasource;
  }

  /**
   * Create a database and grant access to a (new) user
   *
   * @param Datasource $datasource
   * @param string $perm PERM_SUPER, PERM_ADMIN
   */
  public function createDatabase(Datasource $datasource, $perm = DatabaseManagementInterface::PERM_ADMIN) {
    $db = $datasource->getDatabase();
    $user = $datasource->getUsername();
    $pass = $datasource->getPassword();

    $dbh = $this->adminDatasource->createPDO();
    $dbh->exec("CREATE DATABASE `$db`");
    $version = $dbh->query("SELECT version()")->fetchAll()[0]['version()'];
    $versionParts = explode('-', $version);
    $createUserStatement = "CREATE USER";
    $authenticationStatment = "IDENTIFIED BY";
    if (version_compare($versionParts[0], '5.6.0', '>=')) {
      $createUserStatement .= " IF NOT EXISTS";
      $authenticationStatment = "IDENTIFIED WITH mysql_native_password BY";
    }
    switch ($perm) {
      case DatabaseManagementInterface::PERM_SUPER:
        $dbh->exec("$createUserStatement '$user'@'localhost' $authenticationStatment '$pass'");
        $dbh->exec("$createUserStatement '$user'@'%' $authenticationStatment '$pass'");
        $dbh->exec("GRANT ALL ON *.* to '$user'@'localhost' WITH GRANT OPTION");
        $dbh->exec("GRANT ALL ON *.* to '$user'@'%' WITH GRANT OPTION");
        break;

      case DatabaseManagementInterface::PERM_ADMIN:
        $dbh->exec("$createUserStatement '$user'@'localhost' $authenticationStatment '$pass'");
        $dbh->exec("$createUserStatement '$user'@'%' $authenticationStatment '$pass'");
        $dbh->exec("GRANT ALL ON `$db`.* to '$user'@'localhost'");
        $dbh->exec("GRANT ALL ON `$db`.* to '$user'@'%'");
        break;

      default:
        throw new \Exception("Unrecognized permission level");
    }
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
