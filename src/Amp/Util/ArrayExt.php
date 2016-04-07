<?php

namespace Amp\Util;

class ArrayExt {

  public static function fetch($key, $array, $default = NULL) {
    if (is_array($array)) {
      if (array_key_exists($key, $array)) {
        return $array[$key];
      }
      else {
        return $default;
      }
    }
    else {
      throw \Exception("ArrayExt::fetch expects an array as the second argument, but you passed (" . print_r($key, TRUE) . ", " . print_r($array, TRUE) . ", " . print_r($default, TRUE) . ")");
    }
  }

}
