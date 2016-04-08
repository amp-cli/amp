<?php
namespace Amp\Database\MySQLRAMServer;

use Amp\Util\FileExt;
use Amp\Util\Path;

/**
 * Class AppArmor
 * @package Amp\Database\MySQLRAMServer
 *
 * TODO: Consider using tmpfile() instead of managing tmp_path
 */
class AppArmor {
  /**
   * @var string
   */
  public $app_armor_config_file_path;

  /**
   * @var array
   */
  public $app_armor_lines;

  /**
   * @var string
   */
  public $tmp_path;

  /**
   * @param string $app_armor_config_file_path
   */
  public function setConfigFilePath($app_armor_config_file_path) {
    $this->app_armor_config_file_path = $app_armor_config_file_path;
  }

  /**
   * @return string
   */
  public function getConfigFilePath() {
    return $this->app_armor_config_file_path;
  }

  /**
   * @param array $app_armor_lines
   */
  public function setAppArmorLines($app_armor_lines) {
    $this->app_armor_lines = $app_armor_lines;
  }

  /**
   * @return array
   */
  public function getAppArmorLines() {
    return $this->app_armor_lines;
  }

  /**
   * @return array
   */
  public function getFormattedAppArmorLines() {
    $r = array();
    foreach ($this->getAppArmorLines() as $l) {
      $r[] = "$l,\n";
    }
    return $r;
  }

  /**
   * @param mixed $tmp_path
   */
  public function setTmpPath($tmp_path) {
    $this->tmp_path = $tmp_path;
  }

  /**
   * @return bool
   */
  public function isConfigured() {
    if (!file_exists($this->app_armor_config_file_path)) {
      return FALSE;
    }
    $unmatched_lines = array_flip($this->getFormattedAppArmorLines());
    $app_armor_config_file = FileExt::open($this->app_armor_config_file_path, 'r');
    while (($line = fgets($app_armor_config_file)) !== FALSE) {
      if (isset($unmatched_lines[$line])) {
        unset($unmatched_lines[$line]);
      }
    }
    FileExt::close($app_armor_config_file);
    if (empty($unmatched_lines)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @return string
   * @throws \Exception
   */
  public function createNewConfig() {
    $file_system = new \Amp\Util\Filesystem();
    $new_config_file_path = Path::join($this->tmp_path, basename($this->app_armor_config_file_path));
    $file_system->copy($this->app_armor_config_file_path, $new_config_file_path);
    $new_config_file = FileExt::open($new_config_file_path, 'a');
    FileExt::write($new_config_file, "\n");
    foreach ($this->getFormattedAppArmorLines() as $app_armor_line) {
      FileExt::write($new_config_file, $app_armor_line);
    }
    FileExt::close($new_config_file);
    return $new_config_file_path;
  }

  public function configure() {
    $new_config_file_path = $this->createNewConfig();
    $this->runCommand("sudo mv $new_config_file_path {$this->app_armor_config_file_path}");
    $this->runCommand("sudo /etc/init.d/apparmor restart", array('throw_exception_on_nonzero' => FALSE));
  }

  public function runCommand($command, $options = array()) {
    $options['print_command'] = TRUE;
    return \Amp\Util\Shell::run($command);
  }

}