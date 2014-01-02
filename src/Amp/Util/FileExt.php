<?php

namespace Amp\Util;

class FileExt {
  static function close($handle) {
    $result = @fclose($handle);
    if ($result === FALSE) {
      throw new \Exception("Error closing " . print_r($handle, TRUE) . ": " . print_r(error_get_last(), TRUE));
    }
    return $result;
  }

  static function open($file_path, $mode, $use_include_path = false) {
    $result = @fopen($file_path, $mode, $use_include_path);
    if ($result === FALSE) {
      throw new \Exception("Error opening $file_path with mode $mode: " . print_r(error_get_last(), TRUE));
    }
    return $result;
  }

  static function read($handle, $length) {
    $result = @fread($handle, $length);
    if ($result === FALSE) {
      throw new \Exception("Error reading from " . print_r($handle, TRUE) . ": " . print_r(error_get_last(), TRUE));
    }
    return $result;
  }

  static function write($handle, $string) {
    $result = @fwrite($handle, $string);
    if ($result === FALSE) {
      throw new \Exception("Error writing '$string' to " . print_r($handle, TRUE) . ": " . print_r(error_get_last(), TRUE));
    }
    return $result;
  }
}
