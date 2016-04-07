<?php
namespace Amp\Util;
class Process {

  /**
   * Determine the full path to an executable
   *
   * @param $name
   * @return string|FALSE
   */
  public static function findExecutable($name) {
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    foreach ($paths as $path) {
      if (file_exists("$path/$name")) {
        return "$path/$name";
      }
    }
    return FALSE;
  }

}
