<?php
namespace Amp\Httpd;
use Amp\Instance;

/**
 * A null implementation of the HTTPD -- ie use this is
 */
class None implements HttpdInterface {

  /**
   * @param Instance $instance
   *   The webapp being configured.
   */
  public function createVhost(Instance $instance) {
    $root = $instance->getRoot();
    $url = $instance->getUrl();
    file_put_contents('php://stderr', "\n**** Please create the vhost for $url in $root ****\n\n", FILE_APPEND);
  }

  /**
   * @param Instance $instance
   *   The webapp being configured.
   */
  public function dropVhost(Instance $instance) {
    $root = $instance->getRoot();
    $url = $instance->getUrl();
    file_put_contents('php://stderr', "\n**** Please destroy the vhost for $url in $root ****\n\n", FILE_APPEND);
  }

}
