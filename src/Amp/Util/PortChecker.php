<?php
namespace Amp\Util;
class PortChecker {

  /**
   * @var array (string $scheme => int $port)
   */
  protected $defaultPorts;

  /**
   * @param array $defaultPorts
   */
  public function __construct($defaultPorts = NULL) {
    if ($defaultPorts === NULL) {
      $defaultPorts = array(
        'ssh' => 22,
        'http' => 80,
        'https' => 443,
        'mysql' => 3306,
        'postgresql' => 5432,
        'git' => 9418,
      );
    }
    $this->defaultPorts = $defaultPorts;
  }

  /**
   * @param $url
   * @return mixed
   * @throws \RuntimeException
   */
  public function parseUrl($url) {
    $urlParts = parse_url($url);
    if (empty($urlParts['port'])) {
      if (empty($this->defaultPorts[$urlParts['scheme']])) {
        throw new \RuntimeException("Cannot check port for URL -- no default port specified");
      }
      else {
        $urlParts['port'] = $this->defaultPorts[$urlParts['scheme']];
      }
    }
    return $urlParts;
  }

  /**
   * Determine if a service is listening for connections
   *
   * @param string $url eg "http://example.com", "http://example.com:8080", "mysql://user:pass@localhost:8889"
   * @return bool
   */
  public function checkUrl($url) {
    $urlParts = $this->parseUrl($url);
    return $this->checkHostPort($urlParts['host'], $urlParts['port']);
  }

  /**
   * Determine if a service is listening for connections
   *
   * @param string $host
   * @param int $port
   * @return bool
   */
  public function checkHostPort($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 1);
    $result = $fp ? TRUE : FALSE;
    if ($fp !== FALSE) {
      @fclose($fp);
    }
    return $result;

  }

  public function filterUrls($urls) {
    $result = array();
    foreach ($urls as $url) {
      if ($this->checkUrl($url)) {
        $result[] = $url;
      }
    }
    return $result;
  }

}
