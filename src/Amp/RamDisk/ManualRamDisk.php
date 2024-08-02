<?php
namespace Amp\RamDisk;

/**
 * Class ManualRamDisk
 * @package Amp\RamDisk
 *
 * This may or may not be a real ramdisk. We'll assume that the admin has already
 * configured the path.
 */
class ManualRamDisk implements RamDiskInterface {

  /**
   * @var string
   */
  public $ram_disk_path;

  /**
   * @return string
   */
  public function getPath() {
    return $this->ram_disk_path;
  }

  /**
   * @param string $ram_disk_path
   */
  public function setPath($ram_disk_path) {
    $this->ram_disk_path = $ram_disk_path;
  }

  /**
   * @return bool
   */
  public function isMounted() {
    return self::isWriteableDir($this->getPath());
  }

  public function mount() {
    if (self::isWriteableDir($this->getPath())) {
      // Good to go!
      return;
    }

    $parent = dirname($this->getPath());
    if (self::isWriteableDir($parent)) {
      mkdir($this->getPath());
      return;
    }

    throw new \RuntimeException("Cannot find or create mysql data dir: {$this->getPath()}");
  }

  /**
   * @param $path
   * @return bool
   */
  protected static function isWriteableDir($path) {
    return file_exists($path) && is_dir($path) && is_writable($path);
  }

}
