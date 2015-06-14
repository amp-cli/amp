<?php
namespace Amp;
use Amp\Database\Datasource;
use Symfony\Component\Yaml\Yaml;

class Instance {

  /**
   * @var Datasource|NULL database credentials for the service
   */
  private $datasource;

  /**
   * @var string|NULL
   */
  private $name;

  /**
   * @var string|NULL local path to the document root
   */
  private $root;

  /**
   * @var string|NULL public URL of the document root
   */
  private $url;

  /**
   * @var array|NULL Advanced options (key-value pairs).
   */
  private $options;

  public function __construct($name = NULL, $dsn = NULL, $root = NULL, $url = NULL) {
    $this->setName($name);
    $this->setDsn($dsn);
    $this->setRoot($root);
    $this->setUrl($url);
    $this->options = NULL;
  }

  /**
   * Load any advanced options from the .amp.yml file (if
   * it exists).
   *
   * Notes:
   *  - The .amp.yml file will not be changed by amp; as far
   *    we're concerned, it's read-only and unchanging
   *    within the life of an `amp` invocation.
   *  - The top level of the file is broken down into sections.
   *    The 'default' section is a baseline. Additional sections
   *    may be added for each named instance (to override
   *    the defaults).
   *
   * @return array
   *   Key-value pairs.
   */
  public function getOptions() {
    if ($this->options === NULL) {
      $this->options = array();
      if (is_readable($this->getRoot() . '/.amp.yml')) {
        $options = Yaml::parse(file_get_contents($this->getRoot() . '/.amp.yml'));

        // Merge $options['default'] and $options[$name].
        foreach (array('default', $this->getName()) as $section) {
          if (isset($options[$section])) {
            $this->options = array_merge($this->options, $options[$section]);
          }
        }
      }
    }

    return $this->options;
  }

  /**
   * @param Datasource|NULL $datasource
   */
  public function setDatasource($datasource) {
    $this->datasource = $datasource;
  }

  /**
   * @return Datasource|NULL
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
