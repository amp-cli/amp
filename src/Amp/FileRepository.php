<?php
namespace Amp;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A simple/naive data store for storing named entities in a file. Generally suited for small
 * data sets that may be read/written by both the command and by shell users.
 *
 * To work correctly, one must satisfy these conditions:
 * - Extend the class and implement the encode*() and decode*() functions
 * - Set the filename (either using __construct or setFile())
 * - Add and remove objects using the repository methods -- find(), findAll(), put()
 * - After making changes, save by calling save()
 */
abstract class FileRepository {

  /**
   * @var string|NULL path to a config file (YAML)
   */
  private $file = NULL;

  /**
   * @var null|int $fileMode
   */
  private $fileMode = 0640;

  /**
   * @var FileSystem
   */
  private $fs = NULL;

  private $instances = NULL;

  /**
   * @var PidLock|NULL the currently-held lock (or NULL if none is held)
   */
  private $lock;

  /**
   * @var int|NULL seconds to wait for a lock; if NULL, then don't use locking
   */
  private $lockWait;

  public function __construct($file = NULL, $decoder = NULL, $encoder = NULL, $lockWait = NULL) {
    $this->fs = new Filesystem();
    $this->setFile($file);
    $this->lockWait = $lockWait;
  }

  /**
   * @throws \Exception
   * @return array of Instance
   */
  public function findAll() {
    if ($this->instances === NULL) {
      $this->load();
    }
    return $this->instances;
  }

  public function load() {
    if ($this->file === NULL) {
      throw new \Exception(__CLASS__ . ": Missing required property (configFile)");
    }
    $this->lock();
    if ($this->fs->exists($this->file)) {
      $items = $this->decodeDocument(file_get_contents($this->file));
      $this->instances = array();
      foreach ($items as $key => $item) {
        $this->instances[$key] = $this->decodeItem($item);
      }
    }
    else {
      $this->instances = array();
    }
  }

  /**
   * Acquire a read-write lock on this file
   *
   * @throws \RuntimeException
   */
  public function lock() {
    if ($this->lockWait && !$this->lock) {
      $this->lock = new PidLock($this->getFile());
      if (!$this->lock->acquire($this->lockWait)) {
        throw new \RuntimeException("Failed to acquire lock for [{$this->getFile()}]");
      }
    }
  }

  public function save() {
    $this->lock();
    $items = array();
    foreach ($this->instances as $key => $instance) {
      $items[$key] = $this->encodeItem($instance);
    }
    $this->fs->dumpFile($this->getFile(), $this->encodeDocument($items), $this->getFileMode());
  }

  /**
   * @param string $name
   * @return Instance|NULL
   */
  public function find($name) {
    $all = $this->findAll();
    return isset($all[$name]) ? $all[$name] : NULL;
  }

  public function put($name, $obj) {
    $this->instances[$name] = $obj;
  }

  public function remove($name) {
    unset($this->instances[$name]);
  }

  /**
   * @param string $configFile
   */
  public function setFile($configFile) {
    $this->file = $configFile;
  }

  /**
   * @return string
   */
  public function getFile() {
    return $this->file;
  }

  /**
   * @param int|null $fileMode
   */
  public function setFileMode($fileMode) {
    $this->fileMode = $fileMode;
  }

  /**
   * @return int|null
   */
  public function getFileMode() {
    return $this->fileMode;
  }

  /**
   * @param int|NULL $lockWait
   */
  public function setLockWait($lockWait) {
    $this->lockWait = $lockWait;
  }

  /**
   * @return int|NULL
   */
  public function getLockWait() {
    return $this->lockWait;
  }

  /**
   * @param string $string
   * @return array of array
   */
  abstract function decodeDocument($string);

  /**
   * Convert from array to object
   *
   * @param array $array
   * @return object
   */
  abstract function decodeItem($array);

  /**
   * @param array $items a list of arrays representing items
   * @return string
   */
  abstract function encodeDocument($items);

  /**
   * Convert from object to array
   *
   * @param $obj
   * @return array
   */
  abstract function encodeItem($obj);
}
