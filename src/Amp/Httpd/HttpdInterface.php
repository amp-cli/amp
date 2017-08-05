<?php
namespace Amp\Httpd;
use Amp\ServiceInterface;

interface HttpdInterface {

  /**
   * @param string $root local path to document root
   * @param string $url preferred public URL
   * @param string $visibility set to all to listen on all interfaces
   */
  public function createVhost($root, $url, $visibility);

  /**
   * @param string $root local path to document root
   * @param string $url preferred public URL
   */
  public function dropVhost($root, $url);

  public function restart();

}
