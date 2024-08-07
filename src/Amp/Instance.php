<?php
namespace Amp;

use Amp\Database\Datasource;

class Instance {

  /**
   * Database credentials for the service
   *
   * @var \Amp\Database\Datasource|null
   */
  private $datasource;

  /**
   * @var string|null
   */
  private $name;

  /**
   * Local path to the document root
   * @var string|null
   */
  private $root;

  /**
   * Public URL of the document root
   * @var string|null
   */
  private $url;

  /**
   * Which interfaces vhost should be available on (local or all)
   * @var string
   */
  private $visibility;

  public function __construct($name = NULL, $dsn = NULL, $root = NULL, $url = NULL, $visibility = NULL) {
    $this->setName($name);
    $this->setDsn($dsn);
    $this->setRoot($root);
    $this->setUrl($url);
    $this->setVisibility($visibility);
  }

  /**
   * @param \Amp\Database\Datasource|NULL $datasource
   */
  public function setDatasource($datasource) {
    $this->datasource = $datasource;
  }

  /**
   * @return \Amp\Database\Datasource|NULL
   */
  public function getDatasource() {
    return $this->datasource;
  }

  /**
   * @param NULL|string $dsn
   */
  public function setDsn($dsn) {
    if ($dsn !== NULL) {
      $datasource = new Datasource(array(
        'civi_dsn' => $dsn,
      ));
    }
    else {
      $datasource = NULL;
    }
    $this->setDatasource($datasource);
  }

  /**
   * @return NULL|string
   */
  public function getDsn() {
    return ($this->datasource) ? $this->datasource->toCiviDSN() : NULL;
  }

  /**
   * @param NULL|string $name
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * @return NULL|string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @param NULL|string $root
   */
  public function setRoot($root) {
    $this->root = $root;
  }

  /**
   * @return NULL|string
   */
  public function getRoot() {
    return $this->root;
  }

  /**
   * @param NULL|string $url
   */
  public function setUrl($url) {
    $this->url = $url;
  }

  /**
   * @return NULL|string
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * @param string $visibility
   */
  public function setVisibility($visibility) {
    $this->visibility = $visibility;
  }

  /**
   * @return string
   */
  public function getVisibility() {
    return $this->visibility;
  }

  public function getId() {
    return self::makeId($this->getRoot(), $this->getName());
  }

  public static function makeId($root, $name) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      // Windows filenames are not stable identifiers. Normalize.
      $root = strtolower($root);
      $root = strtr($root, '\\', '/');
    }

    if (empty($name)) {
      return $root . '::\'\'';
    }
    else {
      return $root . '::' . $name;
    }
  }

}
