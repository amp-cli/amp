<?php
namespace Amp\Util;

class User {

  /**
   * Determine if a username is well-defined on this system.
   *
   * @param string $user
   * @return mixed user name (if valid)
   * @throws \RuntimeException
   */
  public static function validateUser($user) {
    if (empty($user)) {
      throw new \RuntimeException("Value is required");
    }
    $pw = posix_getpwnam($user);
    if ($pw && isset($pw['uid'])) {
      return $user;
    }
    else {
      throw new \RuntimeException("Invalid username");
    }
  }

  /**
   * Filter a list of possible user names, returning on the valid ones.
   *
   * @param array $users list of usernames (strings)
   * @return array list of usernames (strings)
   */
  public static function filterValidUsers($users) {
    $matches = array();
    foreach ($users as $user) {
      $pw = posix_getpwnam($user);
      if ($pw && isset($pw['uid'])) {
        $matches[] = $user;
      }
    }
    return $matches;
  }

  /**
   * Determine the name of the current user.
   *
   * @return string
   * @throws \Exception
   */
  public static function getCurrentUser() {
    if (strpos(PHP_OS, 'WIN') === FALSE) {
      if (!function_exists('posix_getpwuid')) {
        throw new \Exception('Failed to determine current user name. (Consider installing PHP module "posix".)');
      }
      $uid = posix_geteuid();
      $pw = posix_getpwuid($uid);
      if ($pw && isset($pw['name'])) {
        return $pw['name'];
      }
    }

    if (strpos(PHP_OS, 'WIN') !== FALSE && getenv('username')) {
      // Windows
      return getenv('username');
    }

    throw new \Exception("Failed to determine current user name.");
  }

}
