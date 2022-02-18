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
    if (strpos($name, DIRECTORY_SEPARATOR) !== FALSE) {
      return $name;
    }
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    foreach ($paths as $path) {
      if (file_exists("$path/$name")) {
        return "$path/$name";
      }
    }
    return FALSE;
  }

  /**
   * Determine if $file is a shell script.
   *
   * @param $file
   * @return bool
   */
  public static function isShellScript($file) {
    $firstLine = file_get_contents($file, FALSE, NULL, 0, 120);
    list($firstLine) = explode("\n", $firstLine);
    return (bool) preg_match(';^#.*bin.*sh;', $firstLine);
  }

  /**
   * Resolve a command's path. If the command is a shell wrapper, look for
   * an alternate.
   *
   * @param string $name
   * @return FALSE|string
   */
  public static function findTrueExecutable($name) {
    if (strpos($name, DIRECTORY_SEPARATOR) !== FALSE) {
      return $name;
    }
    $bin = Process::findExecutable($name);
    if (Process::isShellScript($bin)) {
      if (file_exists($bin . '.bin')) {
        return $bin . '.bin';
      }
    }
    return $bin;
  }

}
