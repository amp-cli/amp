<?php
namespace Amp;

/**
 * Acquire a PID-based lock on a file.
 *
 * The lock is owned by a particular PID and remains
 * valid until the PID disappears (or until the lock
 * is released or stolen).
 */
class PidLock {
  /**
   * @var string
   */
  private $file;

  /**
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  private $fs;

  /**
   * @var string
   */
  private $lockFile;

  /**
   * @var int pid of the current process
   */
  private $pid;

  private $minDelayUs = 10000; // 10,000 us == 10ms
  private $maxDelayUs = 200000; // 200,000 us == 200ms == 0.2s

  /**
   * @param string $file the file for which we want a lock
   * @param string|null $lockFile the file which represents the lock; if null, autogenerate
   * @param int|null $pid the process which holds the lock; if null, the current process
   */
  function __construct($file, $lockFile = NULL, $pid = NULL) {
    $this->file = $file;
    $this->lockFile = $lockFile ? $lockFile : "{$file}.lock";
    $this->fs = new \Symfony\Component\Filesystem\Filesystem();
    $this->pid = $pid ? $pid : posix_getpid();
  }

  public function __destruct() {
    $this->release();
  }

  /**
   * @param int $wait max time to wait to acquire lock (seconds)
   * @return bool TRUE if acquired; else false
   */
  function acquire($wait) {
    $waitUs = $wait * 1000 * 1000;

    $totalDelayUs = 0; // total total spent waiting so far (microseconds)
    $nextDelayUs = 0;
    while ($totalDelayUs < $waitUs) {
      if ($nextDelayUs) {
        usleep($nextDelayUs);
        $totalDelayUs += $nextDelayUs;
      }

      if (!$this->fs->exists($this->lockFile)) {
        $this->fs->dumpFile($this->lockFile, $this->pid);
        return TRUE;
      }

      $lockPid = (int) trim(file_get_contents($this->lockFile));
      if ($lockPid == $this->pid) {
        return TRUE;
      }

      if (!posix_getpgid($lockPid)) {
        $this->fs->dumpFile($this->lockFile, $this->pid);
        return TRUE;
      }

      $nextDelayUs = rand($this->minDelayUs, min($this->maxDelayUs, $waitUs - $totalDelayUs));
    }
    return FALSE;
  }

  function release() {
    if ($this->fs->exists($this->lockFile)) {
      $lockPid = (int) trim(file_get_contents($this->lockFile));
      if ($lockPid == $this->pid) {
        $this->fs->remove($this->lockFile);
      }
    }
  }

  function steal() {
    $this->fs->dumpFile($this->lockFile, $this->pid);
  }
}