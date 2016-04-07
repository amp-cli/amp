<?php
namespace Amp\Database;

interface DatabaseManagementInterface {
  /**
   * The user will be able to manipulate all schema elements, including triggers and functions.
   */
  const PERM_SUPER = 'super';

  /**
   * The user will be able to manipulate all tables and views.
   */
  const PERM_ADMIN = 'admin';

  /**
   * Create a datasource representing a new user and database
   *
   * @param string $hint an advisory string; ideally included in $db/$user
   * @return Datasource;
   */
  public function createDatasource($hint);

  /**
   * Create a database and grant access to a (new) user
   *
   * @param Datasource $datasource
   * @param string $perm PERM_SUPER, PERM_ADMIN
   */
  public function createDatabase(Datasource $datasource, $perm = DatabaseManagementInterface::PERM_ADMIN);

  /**
   * Create a database and grant access to a (new) user
   *
   * @param Datasource $datasource
   */
  public function dropDatabase($datasource);

}
