<?php
namespace Amp\Httpd;
use Amp\Util\Filesystem;
use Amp\Permission\PermissionInterface;
use Symfony\Component\Templating\EngineInterface;

class VhostTemplate implements HttpdInterface {
  /**
   * @var string, path to which we should write new config files
   */
  private $dir;

  /**
   * @var Filesystem
   */
  private $fs;

  /**
   * @var string absolute path to a log directory
   */
  private $logDir;

  /**
   * @var PermissionInterface
   */
  private $perm;

  /**
   * @var string, name of the template file
   */
  private $template;

  /**
   * @var EngineInterface
   */
  private $templateEngine;

  /**
   * @var string
   *   Maybe empty, 'NONE', or a command.
   */
  private $restartCommand;

  /**
   * @var int
   */
  private $restartWait = 0;

  /**
   * @var array
   */
  private $httpd_shared_ports;

  public function __construct() {
    $this->fs = new Filesystem();
  }

  /**
   * @param string $root local path to document root
   * @param string $url preferred public URL
   * @param string $visibility set to all to listen on all interfaces
   */
  public function createVhost($root, $url, $visibility = 'local') {
    $parameters = parse_url($url);
    if (!$parameters || !isset($parameters['host'])) {
      throw new \Exception("Failed to parse URL: " . $url);
    }
    if (empty($parameters['port'])) {
      $parameters['port'] = 80;
    }
    $parameters['use_listen'] = !in_array((int) $parameters['port'], $this->getSharedPorts());
    $parameters['root'] = $root;
    $parameters['url'] = $url;
    $parameters['include_vhost_file'] = '';
    $parameters['log_dir'] = $this->getLogDir();
    $parameters['visibility'] = $visibility;
    $content = $this->getTemplateEngine()->render($this->getTemplate(), $parameters);
    $this->fs->dumpFile($this->createFilePath($root, $url), $content);

    $this->setupLogDir();
  }

  public function setupLogDir() {
    $this->fs->mkdir($this->getLogDir());
    $this->getPerm()->applyDirPermission(PermissionInterface::WEB_WRITE, $this->getLogDir());
  }

  /**
   * @param string $root local path to document root
   * @param string $url preferred public URL
   */
  public function dropVhost($root, $url) {
    $this->fs->remove($this->createFilePath($root, $url));
  }

  public function restart() {
    if ($this->restartCommand && $this->restartCommand !== 'NONE') {
      passthru($this->restartCommand, $result);
      if ($result) {
        throw new \RuntimeException("httpd_restart_command failed ($this->restartCommand)");
      }
      if ($this->restartWait) {
        sleep($this->restartWait);
      }
    }
  }

  /**
   * Determine the path to the configuration file for a given host
   *
   * @param $root
   * @param $url
   */
  public function createFilePath($root, $url) {
    $parameters = parse_url($url);
    if (empty($parameters['port'])) {
      $parameters['port'] = 80;
    }
    return $this->getDir() . DIRECTORY_SEPARATOR . $parameters['host'] . '_' . $parameters['port'] . '.conf';
  }

  /**
   * @param string $dir
   */
  public function setDir($dir) {
    $this->dir = $dir;
  }

  /**
   * @return string
   */
  public function getDir() {
    return $this->dir;
  }

  /**
   * @param string $logDir
   */
  public function setLogDir($logDir) {
    $this->logDir = $logDir;
  }

  /**
   * @return string
   */
  public function getLogDir() {
    return $this->logDir;
  }

  /**
   * @param \Amp\Permission\PermissionInterface $perm
   */
  public function setPerm($perm) {
    $this->perm = $perm;
  }

  /**
   * @return \Amp\Permission\PermissionInterface
   */
  public function getPerm() {
    return $this->perm;
  }

  /**
   * @return string
   */
  public function getRestartCommand() {
    return $this->restartCommand;
  }

  /**
   * @param string $restartCommand
   */
  public function setRestartCommand($restartCommand) {
    $this->restartCommand = $restartCommand;
  }

  /**
   * @return int
   */
  public function getRestartWait() {
    return $this->restartWait;
  }

  /**
   * @param int $restartWait
   */
  public function setRestartWait($restartWait) {
    $this->restartWait = $restartWait;
  }

  /**
   * @return array
   *   Array<int>
   */
  public function getSharedPorts() {
    return $this->httpd_shared_ports;
  }

  /**
   * @param int|string|array<int> $httpd_shared_ports
   *   List of ports.
   *   Ex: 80
   *   Ex: array(80, 8080)
   *   Ex: '80,8080'
   */
  public function setSharedPorts($httpd_shared_ports) {
    if (is_string($httpd_shared_ports)) {
      $httpd_shared_ports = explode(',', $httpd_shared_ports);
    }
    foreach (array_keys($httpd_shared_ports) as $k) {
      $httpd_shared_ports[$k] = (int) $httpd_shared_ports[$k];
    }
    $this->httpd_shared_ports = $httpd_shared_ports;
  }


  /**
   * @param string $template
   */
  public function setTemplate($template) {
    $this->template = $template;
  }

  /**
   * @return string
   */
  public function getTemplate() {
    return $this->template;
  }

  /**
   * @param \Symfony\Component\Templating\EngineInterface $templateEngine
   */
  public function setTemplateEngine($templateEngine) {
    $this->templateEngine = $templateEngine;
  }

  /**
   * @return \Symfony\Component\Templating\EngineInterface
   */
  public function getTemplateEngine() {
    return $this->templateEngine;
  }

}
