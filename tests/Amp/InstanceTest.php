<?php
namespace Amp;

class InstanceTest extends \PHPUnit_Framework_TestCase {

  public function optionTestProvider() {
    $cases = array(); // array($root, $name, $option, $expectValue)

    $cases[] = array(__DIR__ . '/InstanceTest/1-default', '', 'foo', 'foo default');
    $cases[] = array(__DIR__ . '/InstanceTest/1-default', '', 'bar', 'bar default');
    $cases[] = array(__DIR__ . '/InstanceTest/1-default', '', 'whiz', NULL);
    $cases[] = array(__DIR__ . '/InstanceTest/1-default', 'alpha', 'foo', 'foo default');
    $cases[] = array(__DIR__ . '/InstanceTest/1-default', 'alpha', 'bar', 'bar default');
    $cases[] = array(__DIR__ . '/InstanceTest/1-default', 'alpha', 'whiz', NULL);

    $cases[] = array(__DIR__ . '/InstanceTest/2-override', '', 'foo', 'foo default');
    $cases[] = array(__DIR__ . '/InstanceTest/2-override', '', 'bar', 'bar default');
    $cases[] = array(__DIR__ . '/InstanceTest/2-override', '', 'whiz', NULL);
    $cases[] = array(__DIR__ . '/InstanceTest/2-override', 'alpha', 'foo', 'foo alpha'); // Overriden!
    $cases[] = array(__DIR__ . '/InstanceTest/2-override', 'alpha', 'bar', 'bar default');
    $cases[] = array(__DIR__ . '/InstanceTest/2-override', 'alpha', 'whiz', NULL);
    $cases[] = array(__DIR__ . '/InstanceTest/2-override', 'omega', 'foo', 'foo default');
    $cases[] = array(__DIR__ . '/InstanceTest/2-override', 'omega', 'bar', 'bar default');
    $cases[] = array(__DIR__ . '/InstanceTest/2-override', 'omega', 'whiz', NULL);

    return $cases;
  }

  /**
   * Test that options in .amp.yml are properly loaded.
   *
   * @param string $root
   *   The path to the webapp root.
   * @param string $name
   *   The name of the webapp (if using multiple apps in the same dir).
   * @param string $option
   *   The name of the option to check.
   * @param mixed $expectValue
   *   The value expected for the option.
   * @dataProvider optionTestProvider
   */
  public function testOptions($root, $name, $option, $expectValue) {
    $i = new Instance($name, NULL, $root, NULL);
    $options = $i->getOptions();
    if ($expectValue === NULL) {
      $this->assertTrue(!isset($options[$option]));
    }
    else {
      $this->assertEquals($expectValue, $options[$option]);
    }
  }

}
