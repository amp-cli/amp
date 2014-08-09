<?php
namespace Amp\RamDisk;

use Symfony\Component\DependencyInjection\ContainerInterface;

class RamDiskFactory {
  /**
   * Guess which implementation of ramdisk is most appropriate to the local system.
   *
   * @param ContainerInterface $container
   * @return object
   * @throws \RuntimeException
   */
  public static function get(ContainerInterface $container) {
    if (preg_match('/Darwin/', PHP_OS)) {
      return $container->get('ram_disk.osx');
    } elseif (preg_match('/Linux/', PHP_OS)) {
      return $container->get('ram_disk.linux');
    } else {
      throw new \RuntimeException("Cannot determine ramdisk provider");
    }
  }
}