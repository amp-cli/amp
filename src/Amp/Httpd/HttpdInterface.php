<?php
namespace Amp\Httpd;
use Amp\Instance;
use Amp\ServiceInterface;

interface HttpdInterface {

  /**
   * @param Instance $instance
   *   The webapp being configured.
   */
  public function createVhost(Instance $instance);

  /**
   * @param Instance $instance
   *   The webapp being configured.
   */
  public function dropVhost(Instance $instance);

}
