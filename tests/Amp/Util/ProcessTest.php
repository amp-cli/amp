<?php
namespace Amp\Util;

class ProcessTest extends \PHPUnit_Framework_TestCase {
  public function testWellknownCommand() {
    $path = Process::findExecutable('ls');
    $this->assertTrue(is_string($path));
  }

  public function testBadCommand() {
    $path = Process::findExecutable('tot-all-yinv-ali-dcom-mand');
    $this->assertTrue($path === FALSE);
  }
}