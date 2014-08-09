<?php
namespace Amp\RamDisk;

class LinuxRamDisk implements RamDiskInterface {
  /**
   * @var string
   */
  public $ram_disk_path;

  /**
   * @var int
   */
  public $size_mb;

  /**
   * @return bool
   */
  public function isMounted() {
    $result = $this->runCommand("stat -f -c '%T' {$this->ram_disk_path}");
    if (trim($result[0]) != 'tmpfs') {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  public function mount() {
    $this->runCommand("sudo mount -t tmpfs -o size={$this->getSizeMb()}m tmpfs {$this->ram_disk_path}");
    $uid = getmyuid();
    $gid = getmygid();
    $this->runCommand("sudo chown $uid:$gid {$this->ram_disk_path}");
    $this->runCommand("chmod 0755 {$this->ram_disk_path}");
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
    $options['print_command'] = TRUE;
    return \Amp\Util\Shell::run($command);
  }

}