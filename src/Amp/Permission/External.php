<?php
namespace Amp\Permission;
use Amp\Util\Filesystem;
use Symfony\Component\Process\Process;

class External implements PermissionInterface {

  /**
   * @var array (string $perm => string $command)
   */
  var $dirCommands;

  /**
   * @var Filesystem
   */
  private $fs;

  public function __construct() {
    $this->dirCommands = array();
    $this->fs = new Filesystem();
  }

  /**
   * Set the permissions for a directory
   *
   * @param string $perm eg PermissionInterface::WEB_WRITE
   * @param string $dir the directory whose permissions should change
   */
  public function applyDirPermission($perm, $dir) {
    if (!$this->fs->exists($dir)) {
      return;
    }

    $dirCommand = strtr($this->getDirCommand($perm), array(
      '{DIR}' => escapeshellarg($dir),
    ));
    if ($dirCommand == '') {
      return;
    }
    $process = new Process($dirCommand);
    $process->run();
    if (!$process->isSuccessful()) {
      throw new \RuntimeException($process->getErrorOutput());
    }
    print $process->getOutput(); // REMOVE
  }

  /**
   * @param string $perm eg PermissionInterface::WEB_WRITE
   * @param string $dirCommand
   */
  public function setDirCommand($perm, $dirCommand) {
    $this->dirCommands[$perm] = $dirCommand;
  }

  /**
   * @param string $perm eg PermissionInterface::WEB_WRITE
   * @return string
   */
  public function getDirCommand($perm) {
    if (isset($this->dirCommands[$perm])) {
      return $this->dirCommands[$perm];
    }
    else {
      throw new \RuntimeException("Missing external command specification for permission: $perm");
    }
  }

  /**
   * Ensure that $command executes without error when applied to a test directory.
   *
   * @param string $testDir
   * @param string $command
   * @return string $command if well-formed
   * @throws \Symfony\Component\Filesystem\Exception, \RuntimeException
   */
  public static function validateDirCommand($testDir, $command) {
    if (empty($command)) {
      throw new \RuntimeException("Command is required");
    }

    $fs = new \Amp\Util\Filesystem();
    $fs->mkdir($testDir);
    $permHandler = new \Amp\Permission\External();
    $permHandler->setDirCommand('examplePerm', $command);
    $permHandler->applyDirPermission('examplePerm', $testDir);
    $fs->remove($testDir);
    return $command;
  }

}
