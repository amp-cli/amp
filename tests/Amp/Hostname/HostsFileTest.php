<?php
namespace Amp\Hostname;

/**
 * @group unit
 */
class HostsFileTest extends \PHPUnit_Framework_TestCase {

  public function getCases() {

    $cases = array();

    $cases[] = array(
      TRUE,
      "",
      "127.0.0.1 example.com\n"
    );
    $cases[] = array(
      TRUE,
      "127.0.0.1 example.com",
      "127.0.0.1 example.com"
    );
    $cases[] = array(
      TRUE,
      "127.0.0.1 example.com\n",
      "127.0.0.1 example.com\n"
    );
    $cases[] = array(
      TRUE,
      "127.0.0.1 example.commie",
      "127.0.0.1 example.commie example.com"
    );
    $cases[] = array(
      TRUE,
      "127.0.0.1 example.commie\n",
      "127.0.0.1 example.commie example.com\n"
    );
    $cases[] = array(
      FALSE,
      "127.0.0.1 example.commie\n",
      "127.0.0.1 example.commie\n127.0.0.1 example.com\n"
    );
    $cases[] = array(
      FALSE,
      "127.1.0.1 example.commie\n127.2.0.1 example.zoo\n",
      "127.1.0.1 example.commie\n127.2.0.1 example.zoo\n127.0.0.1 example.com\n"
    );
    $cases[] = array(
      FALSE,
      "127.1.0.1 example.commie\n127.2.0.1 example.zoo",
      "127.1.0.1 example.commie\n127.2.0.1 example.zoo\n127.0.0.1 example.com\n"
    );

    return $cases;
  }

  /**
   * @param $isGroupByIp
   * @param $origContent
   * @param $expectContent
   * @dataProvider getCases
   */
  public function testAddHostIpScript($isGroupByIp, $origContent, $expectContent) {
    $file = tempnam(sys_get_temp_dir(), 'test-hosts');

    $hostsFile = new HostsFile();
    $hostsFile->setSudo(FALSE);
    $hostsFile->setGroupByIp($isGroupByIp);
    $hostsFile->setIp('127.0.0.1');
    $hostsFile->setFile($file);

    file_put_contents($file, $origContent);
    $hostsFile->createHostname('example.com');
    $this->assertEquals($expectContent, file_get_contents($file));
    unlink($file);
  }

}