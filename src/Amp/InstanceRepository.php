<?php
namespace Amp;
use Symfony\Component\Yaml\Yaml;

class InstanceRepository extends FileRepository {

  /**
   * @var DatabaseManagementInterface
   */
  private $db;

  /**
   * @var HttpdInterface
   */
  private $httpd;

  /**
   * Create a new instance (with given web-root, URL,
   * and DB credentials -- if given). If an existing one
   * exists, it will be overwritten.
   *
   * @param Instance $instance
   * @param bool $useWeb
   * @param bool $useDB
   */
  public function create($instance, $useWeb = TRUE, $useDB = TRUE) {
    if ($useDB) {
      if (!$instance->getDatasource()) {
        $instance->setDatasource($this->db->createDatasource(basename($instance->getRoot()) . $instance->getName()));
      }

      $this->db->dropDatabase($instance->getDatasource());
      $this->db->createDatabase($instance->getDatasource());
    }

    if ($useWeb) {
      if (!$instance->getUrl()) {
        $instance->setUrl('http://localhost:FIXME');
      }

      $this->httpd->dropVhost($instance->getRoot(), $instance->getUrl());
      $this->httpd->createVhost($instance->getRoot(), $instance->getUrl());
    }

    $this->put($instance->getId(), $instance);
  }

  /**
   * Remove the named instance
   * @param $name
   */
  public function remove($name) {
    $instance = $this->find($name);
    if ($instance) {
      if ($instance->getDsn()) {
        $this->db->dropDatabase($instance->getDatasource());
      }

      if ($instance->getUrl()) {
        $this->httpd->dropVhost($instance->getRoot(), $instance->getUrl());
      }
    }
    parent::remove($name);
  }

  // ---------------- Required methods ----------------

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

  // ---------------- Boilerplate ----------------

  /**
   * @param \Amp\DatabaseManagementInterface $db
   */
  public function setDb($db) {
    $this->db = $db;
  }

  /**
   * @return \Amp\DatabaseManagementInterface
   */
  public function getDb() {
    return $this->db;
  }

  /**
   * @param \Amp\HttpdInterface $httpd
   */
  public function setHttpd($httpd) {
    $this->httpd = $httpd;
  }

  /**
   * @return \Amp\HttpdInterface
   */
  public function getHttpd() {
    return $this->httpd;
  }

}
