<?php
namespace Amp\Database;

interface DatabaseManagementInterface {
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
   */
  public function createDatabase(Datasource $datasource);

  /**
   * Create a database and grant access to a (new) user
   *
   * @param Datasource $datasource
   */
  public function dropDatabase($datasource);

}