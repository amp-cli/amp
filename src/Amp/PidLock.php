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
   * Pid of the current process
   * @var int
   */
  private $pid;

  /**
   * 10,000 us == 10ms
   */
  private $minDelayUs = 10000;

  /**
   * 200,000 us == 200ms == 0.2s
   */
  private $maxDelayUs = 200000;

  /**
   * @param string $file the file for which we want a lock
   * @param string|null $lockFile the file which represents the lock; if null, autogenerate
   * @param int|null $pid the process which holds the lock; if null, the current process
   */
  public function __construct($file, $lockFile = NULL, $pid = NULL) {
    if (!$this->hasDeps()) {
      $this->warnDeps();
      return;
    }
    $this->file = $file;
    $this->lockFile = $lockFile ? $lockFile : "{$file}.lock";
    $this->fs = new \Symfony\Component\Filesystem\Filesystem();
    $this->pid = $pid ? $pid : posix_getpid();
  }

  public function __destruct() {
    $this->release();
  }

  /**
   * Determine if we have sufficient APIs to perform
   * locking.
   *
   * @return bool
   */
  public function hasDeps() {
    return function_exists('posix_getpid')
      && function_exists('posix_getpgid')
      && function_exists('usleep');
  }

  /**
   * Display warning about missing APIs.
   */
  public function warnDeps() {
    fwrite(STDERR, "WARNING: " . __CLASS__ . ": POSIX API is unavaiable. Cannot lock resources. Concurrent operations may be problematic.\n");
  }

  /**
   * @param int $wait max time to wait to acquire lock (seconds)
   * @return bool TRUE if acquired; else false
   */
  public function acquire($wait) {
    if (!$this->hasDeps()) {
      return TRUE;
    }

    $waitUs = $wait * 1000 * 1000;

    // total total spent waiting so far (microseconds)
    $totalDelayUs = 0;
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

  public function release() {
    if (!$this->hasDeps()) {
      return;
    }

    if ($this->fs->exists($this->lockFile)) {
      $lockPid = (int) trim(file_get_contents($this->lockFile));
      if ($lockPid == $this->pid) {
        $this->fs->remove($this->lockFile);
      }
    }
  }

  public function steal() {
    if (!$this->hasDeps()) {
      return;
    }
    $this->fs->dumpFile($this->lockFile, $this->pid);
  }

}
