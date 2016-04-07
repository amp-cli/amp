<?php
namespace Amp\RamDisk;

use Amp\Util\Shell;

class OsxRamDisk implements RamDiskInterface {
  /**
   * @var string
   */
  public $ram_disk_path;

  /**
   * @var int
   */
  public $size_mb;

  /**
   * @var string the file which stores the name of the ramdisk block device
   */
  public $dev_file;

  /**
   * @return bool
   */
  public function isMounted() {
    list($stdout, $stderr) = $this->runCommand("mount");
    $lines = explode("\n", $stdout);
    foreach ($lines as $line) {
      if (preg_match(':([^ ]+) on (/.+) \((.+)\):', $line, $matches)) {
        if (rtrim($matches[2], '/') == rtrim($this->ram_disk_path)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  public function mount() {
    if (!is_dir($this->getPath())) {
      throw new \RuntimeException("Cannot start ramdisk: Missing directory ({$this->getPath()})");
    }

    $sectors = $this->getSizeMb() * 1024 * 1024 / 512;
    list ($stdout, $stderr) = $this->runCommand("hdid -nomount ram://{$sectors}");
    $device = trim($stdout);
    if ($stderr) {
      throw new \RuntimeException("Unexpected error output");
    }
    $this->setDevice($device);

    $this->runCommand(Shell::fmt(
      'newfs_hfs', '-v', 'AMP RAM Disk', $device
    ));
    $this->runCommand(Shell::fmt(
      'mount', '-o', 'noatime', '-t', 'hfs', $device, $this->getPath()
    ));
  }

//  public function unmount() {
//    $device = $this->getDevice();
//    if (!$device) throw \Exception
//    umount "$this->getPath()"
//    diskutil eject "$device"
//    $this->setDevice(NULL);
//  }

  /**
   * @param null|string $dev the name of the OSX ram block device
   */
  public function setDevice($dev) {
    if ($dev) {
      file_put_contents($this->getDevFile(), $dev);
    }
    elseif (file_exists($this->getDevFile())) {
      unlink($this->getDevFile());
    }
  }

  /**
   * @return null|string the name of the OSX ram block device
   */
  public function getDevice() {
    if (file_exists($this->getDevFile())) {
      return trim(file_get_contents($this->getDevFile()));
    }
    else {
      return NULL;
    }
  }

  /**
   * @param string $devFile
   */
  public function setDevFile($devFile) {
    $this->dev_file = $devFile;
  }

  /**
   * @return string
   */
  public function getDevFile() {
    return $this->dev_file;
  }

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
   * @param int $sizeMb
   */
  public function setSizeMb($sizeMb) {
    $this->size_mb = $sizeMb;
  }

  /**
   * @return int
   */
  public function getSizeMb() {
    return $this->size_mb;
  }

  public function runCommand($command, $options = array()) {
    // $options['print_command'] = TRUE;
    return \Amp\Util\Shell::run($command, $options);
  }

}
