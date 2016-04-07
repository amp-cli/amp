<?php
namespace Amp\Util;
class String {
  const ALPHANUMERIC = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

  /**
   * Generate a random string
   *
   * @param $len
   * @param $alphabet
   * @return string
   */
  public static function createRandom($len, $alphabet = self::ALPHANUMERIC) {
    $alphabetSize = strlen($alphabet);
    $result = '';
    for ($i = 0; $i < $len; $i++) {
      $result .= $alphabet{rand(1, $alphabetSize) - 1};
    }
    return $result;
  }

  /**
   * @param string $hint a string that should appear in the name
   * @param int $fullLen total max# chars. must be at least +1 over $randLen
   * @param int $randLen #random chars
   * @return string
   */
  public static function createHintedRandom($hint, $fullLen, $randLen, $randAlphabet = self::ALPHANUMERIC) {
    $hint = preg_replace('/[^a-zA-Z0-9]/', '', $hint);
    return substr($hint, 0, $fullLen - 1 - $randLen) . '_' . \Amp\Util\String::createRandom($randLen, $randAlphabet);
  }

}
