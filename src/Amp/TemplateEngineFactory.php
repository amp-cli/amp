<?php
namespace Amp;

use Symfony\Component\Templating\PhpEngine;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\Loader\FilesystemLoader;

class TemplateEngineFactory {

  public static function get() {
    $loader = new FilesystemLoader(__DIR__ . '/views/%name%');
    return new PhpEngine(new TemplateNameParser(), $loader);
  }

}
