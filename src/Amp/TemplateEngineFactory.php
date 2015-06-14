<?php
namespace Amp;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\Loader\FilesystemLoader;
use Symfony\Component\Templating\EngineInterface;

class TemplateEngineFactory {

  /**
   * @param Instance|NULL $instance
   * @return EngineInterface
   */
  public function create($instance = NULL) {
    $paths = array();
    if ($instance !== NULL) {
      if ($instance->getName()) {
        $paths[] = $instance->getRoot() . '/.amp/' . $instance->getName() . '/views/%name%';
      }
      $paths[] = $instance->getRoot() . '/.amp/default/views/%name%';
    }
    $paths[] = __DIR__ . '/views/%name%';

    $loader = new FilesystemLoader($paths);
    return new PhpEngine(new TemplateNameParser(), $loader);
  }

}
