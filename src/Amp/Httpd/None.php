<?php
namespace Amp\Httpd;

/**
 * A null implementation of the HTTPD -- ie use this is
 */
class None implements HttpdInterface {

  /**
   * @param string $root local path to document root
   * @param string $url preferred public URL
   */
  public function createVhost($root, $url) {
    file_put_contents('php://stderr', "\n**** Please create the vhost for $url in $root ****\n\n", FILE_APPEND);
  }

  /**
   * @param string $root local path to document root
   * @param string $url preferred public URL
   */
  public function dropVhost($root, $url) {
    file_put_contents('php://stderr', "\n**** Please destroy the vhost for $url in $root ****\n\n", FILE_APPEND);
  }

}
