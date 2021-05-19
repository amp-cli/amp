<?php
namespace Amp\Util;

/**
 * @group unit
 */
class ProcessTest extends \PHPUnit\Framework\TestCase {
  public function testWellknownCommand(): void {
    $path = Process::findExecutable('ls');
    $this->assertTrue(is_string($path));
  }

  public function testBadCommand(): void {
    $path = Process::findExecutable('tot-all-yinv-ali-dcom-mand');
    $this->assertTrue($path === FALSE);
  }
}
