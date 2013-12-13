<?php
namespace Amp;
use Symfony\Component\Yaml\Yaml;

class InstanceRepository extends FileRepository {
  /**
   * @param string $string
   * @return array of array
   */
  function decodeDocument($string) {
    return Yaml::parse($string);
  }

  /**
   * @param array $items a list of arrays representing items
   * @return string
   */
  function encodeDocument($items) {
    return Yaml::dump($items);
  }

  /**
   * @param array $array
   * @return Instance
   */
  public function decodeItem($array) {
    return new Instance(@$array['name'], @$array['dsn'], @$array['root'], @$array['url']);
  }

  /**
   * @param Instance $instance
   * @return array
   */
  public function encodeItem($instance) {
    return array(
      'name' => $instance->getName(),
      'dsn' => $instance->getDsn(),
      'root' => $instance->getRoot(),
      'url' => $instance->getUrl(),
    );
  }

}
