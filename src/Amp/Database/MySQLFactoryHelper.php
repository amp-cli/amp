<?php
namespace Amp\Database;

use Amp\Util\Shell;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class MySQLFactoryHelper {

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @return DatabaseManagementInterface
   */
  public static function createRAMServer(ContainerInterface $container) {
    list ($mysqldBin) = Shell::run("which mysqld");

    $server = new MySQLRAMServer();
    $server->setRamDisk($container->get('ram_disk'));
    $server->setMySQLRamServerPort($container->getParameter('mysql_ram_server_port'));
    $server->setDefaultDataFiles(self::findDataFiles($mysqldBin));
    if (file_exists("/etc/apparmor.d")) {
      $server->setAppArmor($container->get('app_armor.mysql_ram_disk'));
    }
    return $server;
  }

  public static function findDataFiles($mysqldBin) {
    $filesets = array();

    if (preg_match(';MAMP;', $mysqldBin)) {
      $filesets['mamp'] = array(
        '/Applications/MAMP/Library/share/mysql_system_tables.sql',
        '/Applications/MAMP/Library/share/mysql_system_tables_data.sql',
      );
    }

    if (preg_match(';/usr/local/mysql.*/bin;', $mysqldBin)) {
      $filesets['mysql.com'] = array(
        '/usr/local/mysql/share/mysql_system_tables.sql',
        '/usr/local/mysql/share/mysql_system_tables_data.sql',
      );
    }

    if (preg_match(';/usr/bin;', $mysqldBin)) {
      $filesets['debian'] = array(
        '/usr/share/mysql/mysql_system_tables.sql',
        '/usr/share/mysql/mysql_system_tables_data.sql',
      );
    }

    $fs = new Filesystem();;

    foreach ($filesets as $fileset) {
      if ($fs->exists($fileset)) {
        return new \ArrayObject($fileset);
      }
    }

    throw new \RuntimeException("Failed to locate MySQL initialization files");
  }

}