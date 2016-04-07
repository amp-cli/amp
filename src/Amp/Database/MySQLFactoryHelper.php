<?php
namespace Amp\Database;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class MySQLFactoryHelper {

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @return DatabaseManagementInterface
   */
  public static function createRAMServer(ContainerInterface $container) {
    $server = new MySQLRAMServer();
    $server->setRamDisk($container->get('ram_disk'));
    $server->setMySQLRamServerPort($container->getParameter('mysql_ram_server_port'));
    $server->setDefaultDataFiles(self::findDataFiles());
    if (file_exists("/etc/apparmor.d")) {
      $server->setAppArmor($container->get('app_armor.mysql_ram_disk'));
    }
    return $server;
  }

  public static function findDataFiles() {
    $filesets = array(
      'mamp' => array(
        '/Applications/MAMP/Library/share/mysql_system_tables.sql',
        '/Applications/MAMP/Library/share/mysql_system_tables_data.sql',
      ),
      'debian' => array(
        '/usr/share/mysql/mysql_system_tables.sql',
        '/usr/share/mysql/mysql_system_tables_data.sql',
      ),
    );

    $fs = new Filesystem();;

    foreach ($filesets as $fileset) {
      if ($fs->exists($fileset)) {
        return new \ArrayObject($fileset);
      }
    }

    throw new \RuntimeException("Failed to locate MySQL initialization files");
  }

}