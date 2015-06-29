<?php
namespace Amp\Database;
use Amp\Database\DatabaseManagementInterface;
use Amp\Database\Datasource;
use Exception;

class MySQLPrecreated implements DatabaseManagementInterface {
  static protected $db_seq = 1;

  /**
   * @var Datasource
   */
  protected $adminDatasource = NULL;

  /**
   * @return bool
   */
  public function isRunning() {
    return TRUE;
  }

  /**
   * @param string $dsn
   */
  public function setAdminDsn($dsn) {
    $this->adminDatasource = NULL;
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
    $dsnPattern = getenv('PRECREATED_DSN_PATTERN');

    if ( empty( $dsnPattern ) ) {
        throw new Exception('Must set PRECREATED_DSN_PATTERN to use MySQLPrecreated.');
    }
    $dsn = str_replace('{{db_seq}}', self::$db_seq++, $dsnPattern);

    $datasource = new Datasource(array(
        'civi_dsn' => $dsn
    ));

    return $datasource;
  }

  /**
   * Create a database and grant access to a (new) user
   *
   * @param Datasource $datasource
   * @param string $perm PERM_SUPER, PERM_ADMIN
   */
  public function createDatabase(Datasource $datasource, $perm = DatabaseManagementInterface::PERM_ADMIN) {
    // do nothing
  }

  public function dropDatabase($datasource) {
    // do nothing
  }
}
