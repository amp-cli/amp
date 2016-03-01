<?php
namespace Amp\Httpd;
use Amp\ServiceInterface;

interface HttpdInterface {

  /**
   * @param string $root local path to document root
   * @param string $url preferred public URL
   */
  public function createVhost($root, $url);

  /**
   * @param string $root local path to document root
   * @param string $url preferred public URL
   */
  public function dropVhost($root, $url);

}
