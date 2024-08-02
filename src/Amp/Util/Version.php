<?php

namespace Amp\Util;

class Version {

  /**
   * Perform a series of version-comparisons.
   *
   * For example, suppose your is to check this 3-value comparison:  "4.2.1 <= x <= 4.9.44"
   *
   * @param array $seq
   *   List of values and comparison-operators
   *   Ex: compare('4.2', '<=', $x, '<=', '4.9.44')
   * @return bool
   *   TRUE if all comparisons in the sequence are TRUE.
   *   FALSE if any constraints fail
   */
  public static function compare(...$seq) {
    $left = array_shift($seq);
    while (!empty($seq)) {
      $op = array_shift($seq);
      $right = array_shift($seq);
      if (!version_compare($left, $right, $op)) {
        return FALSE;
      }
      $left = $right;
    }
    return TRUE;
  }

}
