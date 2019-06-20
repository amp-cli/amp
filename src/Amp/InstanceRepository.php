<?php
namespace Amp;
use Amp\Database\DatabaseManagementInterface;
use Symfony\Component\Yaml\Yaml;

class InstanceRepository extends FileRepository {

  /**
   * @var string
   * A list of directory names that frequently appear at the final part of
   * a web-root. These are meaningless in terms of providing hints for
   * DB naming.
   */
  private $dbHintBlacklist = 'web|www|htdocs';

  /**
   * @var DatabaseManagementInterface
   */
  private $db;

  /**
   * @var \Amp\Hostname\HostnameInterface
   */
  private $hosts;

  /**
   * @var \Amp\Httpd\HttpdInterface
   */
  private $httpd;

  /**
   * @var string
   *   ex: 'local', 'all'.
   */
  private $defaultVisibility;

  /**
   * @var array
   *   Ex: Array('httpd' => array(Instance)).
   */
  private $dirty = array();

  /**
   * Create a new instance (with given web-root, URL,
   * and DB credentials -- if given). If an existing one
   * exists, it will be overwritten.
   *
   * @param Instance $instance
   * @param bool $useWeb
   * @param bool $useDB
   * @param string $perm PERM_SUPER, PERM_ADMIN
   */
  public function create($instance, $useWeb = TRUE, $useDB = TRUE, $perm = DatabaseManagementInterface::PERM_ADMIN) {
    if ($useDB) {
      if (!$instance->getDatasource()) {
        $instance->setDatasource($this->db->createDatasource($this->createDbHint($instance)));
      }

      $this->db->dropDatabase($instance->getDatasource());
      $this->db->createDatabase($instance->getDatasource(), $perm);
    }

    if ($useWeb) {
      if (!$instance->getUrl()) {
        $instance->setUrl('http://localhost:7979');
      }

      $this->dirty['httpd'][] = $instance;
      $this->httpd->dropVhost($instance->getRoot(), $instance->getUrl());
      $visibility = $instance->getVisibility() ? $instance->getVisibility() : $this->getDefaultVisibility();
      $this->httpd->createVhost($instance->getRoot(), $instance->getUrl(), $visibility);

      $hostname = parse_url($instance->getUrl(), PHP_URL_HOST);
      $this->hosts->createHostname($hostname);
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
        $this->dirty['httpd'][] = $instance;
        $this->httpd->dropVhost($instance->getRoot(), $instance->getUrl());
      }
    }
    parent::remove($name);
  }

  public function save() {
    parent::save();
    if (!empty($this->dirty['httpd'])) {
      $this->httpd->restart();
      unset($this->dirty['httpd']);
    }
  }

  // ---------------- Required methods ----------------

  /**
   * @param string $string
   * @return array of array
   */
  public function decodeDocument($string) {
    return Yaml::parse($string);
  }

  /**
   * @param array $items a list of arrays representing items
   * @return string
   */
  public function encodeDocument($items) {
    return Yaml::dump($items);
  }

  /**
   * @param array $array
   * @return Instance
   */
  public function decodeItem($array) {
    return new Instance(@$array['name'], @$array['dsn'], @$array['root'], @$array['url'], @$array['visibility']);
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
      'visibility' => $instance->getVisibility(),
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
   * @return Hostname\HostnameInterface
   */
  public function getHosts() {
    return $this->hosts;
  }

  /**
   * @param Hostname\HostnameInterface $hosts
   */
  public function setHosts($hosts) {
    $this->hosts = $hosts;
  }

  /**
   * @param \Amp\Httpd\HttpdInterface $httpd
   */
  public function setHttpd($httpd) {
    $this->httpd = $httpd;
  }

  /**
   * @return \Amp\Httpd\HttpdInterface
   */
  public function getHttpd() {
    return $this->httpd;
  }

  /**
   * @return string
   */
  public function getDefaultVisibility() {
    return $this->defaultVisibility;
  }

  /**
   * @param string $defaultVisibility
   */
  public function setDefaultVisibility($defaultVisibility) {
    $this->defaultVisibility = $defaultVisibility;
  }

  /**
   * @param Instance $instance
   * @return string
   */
  private function createDbHint($instance) {
    $ds = '[/' . DIRECTORY_SEPARATOR . ']';
    $root = preg_replace(";($ds)({$this->dbHintBlacklist})($ds?)\$;", '', $instance->getRoot());
    return basename($root) . $instance->getName();
  }

}
