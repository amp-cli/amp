<?php
namespace Amp\Hostname;
use Amp\ServiceInterface;

interface HostnameInterface {

  /**
   * @param string $hostname The hostname to register
   */
  public function createHostname($hostname);

}
