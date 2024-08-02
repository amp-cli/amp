<?php
namespace Amp\Hostname;

use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Class HostsFile
 * @package Amp\Hostname
 *
 * Note: Linux and OSX have slightly different conventions for /etc/hosts.
 *
 */
class HostsFile implements HostnameInterface {

  /**
   * @var bool
   */
  protected $sudo;

  /**
   * @var string
   */
  protected $file;

  /**
   * @var string
   */
  protected $ip;

  /**
   * @var bool|null
   */
  protected $groupByIp = NULL;

  /**
   * @param string $hostname The hostname to register
   */
  public function createHostname($hostname) {
    $content = file_get_contents($this->file);
    $lines = explode("\n", $content);

    $matchHostLine = $this->findLine($lines, function ($ip, $hostnames) use ($hostname) {
      return in_array($hostname, $hostnames);
    });
    if ($matchHostLine) {
      return;
    }

    $f = new PhpExecutableFinder();
    $php = $f->find();
    if (!$php) {
      throw new \RuntimeException("Failed to determine PHP interpreter");
    }

    $scriptFile = tempnam(sys_get_temp_dir(), 'fix-hosts-php-');
    file_put_contents($scriptFile, "<?php\n" . $this->createScript($content, $lines, $hostname));

    if ($this->isSudo()) {
      echo "Register host \"$hostname\" ($this->ip) in \"$this->file\" via helper \"$scriptFile\".\n";
      passthru("sudo  " . escapeshellarg($php) . " " . escapeshellarg($scriptFile), $return);
    }
    else {
      passthru(escapeshellcmd($php) . " " . escapeshellarg($scriptFile), $return);
    }
    if ($return) {
      throw new \RuntimeException("Failed to update hosts file ($this->file) with ($this->ip $hostname) [$scriptFile]");
    }
    unlink($scriptFile);
  }

  protected function createScript($content, $lines, $hostname) {
    $self = $this;

    $isGroupByIp = $this->isGroupByIp();
    $matchIpLine = $this->findLine($lines, function ($ip) use ($self) {
      return $ip === $self->getIp();
    });
    if ($isGroupByIp && $matchIpLine) {
      $newLine = $matchIpLine . ' ' . $hostname;
      $cmd = '
        $lines = explode("\n", file_get_contents(' . var_export($this->file, 1) . '));
        foreach ($lines as $k => $l) { if ($l == ' . var_export($matchIpLine, 1) . ') {$lines[$k] = ' . var_export($newLine, 1) . ';} }
        file_put_contents(' . var_export($this->file, 1) . ', implode("\n", $lines));
      ';
    }
    else {
      $newLine = $this->ip . " " . $hostname . "\n";
      if ($content && $content[strlen($content) - 1] !== "\n") {
        $newLine = "\n$newLine";
      }
      $cmd = 'file_put_contents(' . var_export($this->file, 1) . ',' . var_export($newLine, 1) . ', FILE_APPEND);';
    }
    return $cmd;
  }

  protected function isGroupByIp() {
    if ($this->groupByIp !== NULL) {
      return $this->groupByIp;
    }
    if (preg_match('/Darwin/', PHP_OS)) {
      return FALSE;
    }
    elseif (preg_match('/Linux/', PHP_OS)) {
      return TRUE;
    }
    else {
      throw new \RuntimeException("Cannot determine preferred /etc/hosts format");
    }
  }

  /**
   * @param bool|null $groupByIp
   */
  public function setGroupByIp($groupByIp) {
    $this->groupByIp = $groupByIp;
  }

  /**
   * @param array $lines
   * @param callback $callback
   *   function(string $ip, array $hostnames, string $line).
   * @return string|NULL
   *   The matching $line.
   */
  protected function findLine($lines, $callback) {
    foreach ($lines as $line) {
      $parts = explode(' ', trim(preg_replace('/[ \t\r]+/', ' ', $line)));
      $ip = array_shift($parts);
      if ($callback($ip, $parts, $line)) {
        return $line;
      }
    }
    return NULL;
  }

  /**
   * @return boolean
   */
  public function isSudo() {
    return $this->sudo;
  }

  /**
   * @param boolean $sudo
   */
  public function setSudo($sudo) {
    $this->sudo = $sudo;
  }

  /**
   * @return string
   */
  public function getFile() {
    return $this->file;
  }

  /**
   * @param string $file
   */
  public function setFile($file) {
    $this->file = $file;
  }

  /**
   * @return string
   */
  public function getIp() {
    return $this->ip;
  }

  /**
   * @param string $ip
   */
  public function setIp($ip) {
    $this->ip = $ip;
  }

}
