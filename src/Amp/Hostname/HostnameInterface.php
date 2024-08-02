<?php
namespace Amp\Hostname;

interface HostnameInterface {

  /**
   * @param string $hostname The hostname to register
   */
  public function createHostname($hostname);

}
