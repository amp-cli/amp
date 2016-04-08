<?php
namespace Amp\Database\MySQLRAMServer;

class MySQLCnfFactoryTest extends \PHPUnit_Framework_TestCase {

  public function getCases() {

    $cases = array();

    $cases[] = array(
      "",
      array(
        'zoosh',
        'wish bash goooof',
      ),
      FALSE,
      "\nzoosh,\nwish bash goooof,\n",
    );

    $cases[] = array(
      "\nzoosh,\nwish bash goooof,\n",
      array(
        'zoosh',
        'wish bash goooof',
      ),
      TRUE,
      "\nzoosh,\nwish bash goooof,\n",
    );

    return $cases;
  }

  /**
   * @param $origContent
   * @param $lines
   * @param $expectConfigured
   * @param $expectContent
   * @dataProvider getCases
   */

  public function testCreateNewConfig($origContent, $lines, $expectConfigured, $expectContent) {
    $cfgFile = tempnam(sys_get_temp_dir(), 'test-app-armor');
    file_put_contents($cfgFile, $origContent);

    $appArmor = new AppArmor();
    $appArmor->setConfigFilePath($cfgFile);
    $appArmor->setAppArmorLines($lines);
    $appArmor->setTmpPath(sys_get_temp_dir());

    $this->assertEquals($expectConfigured, $appArmor->isConfigured());
    if (!$expectConfigured) {
      $newCfgFile = $appArmor->createNewConfig();
      $this->assertEquals($expectContent, file_get_contents($newCfgFile));
    }
    else {
      $this->assertEquals($expectContent, file_get_contents($cfgFile));
    }
  }

}