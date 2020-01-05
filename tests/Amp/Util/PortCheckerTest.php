<?php
namespace Amp\Util;

/**
 * @group unit
 */
class PortCheckerTest extends \PHPUnit_Framework_TestCase {
  const EXAMPLE_PORTLESS_URL = "http://www.google.com";

  function exampleUrls() {
    $cases = array();
    $cases[] = array(self::EXAMPLE_PORTLESS_URL . ':13579', 80, FALSE);
    $cases[] = array(self::EXAMPLE_PORTLESS_URL . ':13579', 13579, FALSE);
    $cases[] = array(self::EXAMPLE_PORTLESS_URL, 80, TRUE);
    $cases[] = array(self::EXAMPLE_PORTLESS_URL . ':80', 13579, TRUE);
    return $cases;
  }

  /**
   * @dataProvider exampleUrls
   * @param $url
   * @param $defaultPort
   * @param $expectedResult
   */
  public function testCheckUrl($url, $defaultPort, $expectedResult) {
    $checker = new \Amp\Util\PortChecker();
    $this->assertEquals($expectedResult, $checker->checkUrl($url, $defaultPort));
    //$this->assertEquals("foo", "bar");
  }

  /**
   * If calling checkPort without an explicit port or an default port,
   * throw an exception
   *
   * @expectedException Exception
   */
  public function testCheckUrlException() {
    $checker = new \Amp\Util\PortChecker();
    $checker->checkUrl('invalidscheme://example.com');
  }

  public function testFilterUrls() {
    $checker = new \Amp\Util\PortChecker();
    $input = array(
      self::EXAMPLE_PORTLESS_URL,
      self::EXAMPLE_PORTLESS_URL . ':13579',
    );
    $expected = array(
      self::EXAMPLE_PORTLESS_URL,
    );
    $this->assertEquals($expected, $checker->filterUrls($input));
  }
}