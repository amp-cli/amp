<?php

namespace Amp\Util;

class Expr {

  /**
   * @var\Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Ex: $container->setParameter('name', 'world');
   */
  protected $container;

  /**
   * Expr constructor.
   * @param $container
   */
  public function __construct($container) {
    $this->container = $container;
  }

  /**
   * @param string $expr
   *   Ex: "echo Hello %name%".
   * @return string
   *   Ex: "echo Hello world".
   */
  public function evaluate($expr) {
    $container = $this->container;
    $callback = function ($matches) use ($expr, $container) {
      $var = $matches[2];
      if ($container->hasParameter($var)) {
        return $container->getParameter($var);
      }
      else {
        throw new \Exception("Unrecognized parameter \"$var\" in command \"$expr\".");
      }
    };
    return preg_replace_callback('/(%([a-zA-Z0-9_\.]+)%)/', $callback, $expr);
  }

  public function getParameter($name) {
    return $this->evaluate($this->container->getParameter($name));
  }

}