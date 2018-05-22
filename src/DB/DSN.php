<?php
namespace DB;

/**
 * Database independent query interface
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Database
 * @package    DB
 * @author     Stig Bakken <ssb@php.net>
 * @author     Tomas V.V.Cox <cox@idecnet.com>
 * @author     Daniel Convissor <danielc@php.net>
 * @copyright  1997-2007 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id: DB.php 315557 2011-08-26 14:32:35Z danielc $
 * @link       http://pear.php.net/package/DB
 */

/**
 * Based on PEAR's DB.php
 */
class DSN {

  /**
   * Parse a data source name
   *
   * Additional keys can be added by appending a URI query string to the
   * end of the DSN.
   *
   * The format of the supplied DSN is in its fullest form:
   * <code>
   *  phptype(dbsyntax)://username:password@protocol+hostspec/database?option=8&another=true
   * </code>
   *
   * Most variations are allowed:
   * <code>
   *  phptype://username:password@protocol+hostspec:110//usr/db_file.db?mode=0644
   *  phptype://username:password@hostspec/database_name
   *  phptype://username:password@hostspec
   *  phptype://username@hostspec
   *  phptype://hostspec/database
   *  phptype://hostspec
   *  phptype(dbsyntax)
   *  phptype
   * </code>
   *
   * @param string $dsn Data Source Name to be parsed
   *
   * @return array an associative array with the following keys:
   *  + phptype:  Database backend used in PHP (mysql, odbc etc.)
   *  + dbsyntax: Database used with regards to SQL syntax etc.
   *  + protocol: Communication protocol to use (tcp, unix etc.)
   *  + hostspec: Host specification (hostname[:port])
   *  + database: Database to use on the DBMS server
   *  + username: User name for login
   *  + password: Password for login
   */
  public static function parseDSN($dsn)
  {
    $parsed = array(
      'phptype'  => FALSE,
      'dbsyntax' => FALSE,
      'username' => FALSE,
      'password' => FALSE,
      'protocol' => FALSE,
      'hostspec' => FALSE,
      'port'     => FALSE,
      'socket'   => FALSE,
      'database' => FALSE,
    );

    if (is_array($dsn)) {
      $dsn = array_merge($parsed, $dsn);
      if (!$dsn['dbsyntax']) {
        $dsn['dbsyntax'] = $dsn['phptype'];
      }
      return $dsn;
    }

    // Find phptype and dbsyntax
    if (($pos = strpos($dsn, '://')) !== FALSE) {
      $str = substr($dsn, 0, $pos);
      $dsn = substr($dsn, $pos + 3);
    } else {
      $str = $dsn;
      $dsn = NULL;
    }

    // Get phptype and dbsyntax
    // $str => phptype(dbsyntax)
    if (preg_match('|^(.+?)\((.*?)\)$|', $str, $arr)) {
      $parsed['phptype']  = $arr[1];
      $parsed['dbsyntax'] = !$arr[2] ? $arr[1] : $arr[2];
    } else {
      $parsed['phptype']  = $str;
      $parsed['dbsyntax'] = $str;
    }

    if (empty($dsn)) {
      return $parsed;
    }

    // Get (if found): username and password
    // $dsn => username:password@protocol+hostspec/database
    if (($at = strrpos($dsn,'@')) !== FALSE) {
      $str = substr($dsn, 0, $at);
      $dsn = substr($dsn, $at + 1);
      if (($pos = strpos($str, ':')) !== FALSE) {
        $parsed['username'] = rawurldecode(substr($str, 0, $pos));
        $parsed['password'] = rawurldecode(substr($str, $pos + 1));
      } else {
        $parsed['username'] = rawurldecode($str);
      }
    }

    // Find protocol and hostspec

    if (preg_match('|^([^(]+)\((.*?)\)/?(.*?)$|', $dsn, $match)) {
      // $dsn => proto(proto_opts)/database
      $proto       = $match[1];
      $proto_opts  = $match[2] ? $match[2] : FALSE;
      $dsn         = $match[3];

    } else {
      // $dsn => protocol+hostspec/database (old format)
      if (strpos($dsn, '+') !== FALSE) {
        list($proto, $dsn) = explode('+', $dsn, 2);
      }
      if (strpos($dsn, '/') !== FALSE) {
        list($proto_opts, $dsn) = explode('/', $dsn, 2);
      } else {
        $proto_opts = $dsn;
        $dsn = NULL;
      }
    }

    // process the different protocol options
    $parsed['protocol'] = (!empty($proto)) ? $proto : 'tcp';
    $proto_opts = rawurldecode($proto_opts);
    if (strpos($proto_opts, ':') !== FALSE) {
      list($proto_opts, $parsed['port']) = explode(':', $proto_opts);
    }
    if ($parsed['protocol'] == 'tcp') {
      $parsed['hostspec'] = $proto_opts;
    } elseif ($parsed['protocol'] == 'unix') {
      $parsed['socket'] = $proto_opts;
    }

    // Get dabase if any
    // $dsn => database
    if ($dsn) {
      if (($pos = strpos($dsn, '?')) === FALSE) {
        // /database
        $parsed['database'] = rawurldecode($dsn);
      } else {
        // /database?param1=value1&param2=value2
        $parsed['database'] = rawurldecode(substr($dsn, 0, $pos));
        $dsn = substr($dsn, $pos + 1);
        if (strpos($dsn, '&') !== FALSE) {
          $opts = explode('&', $dsn);
        } else { // database?param1=value1
          $opts = array($dsn);
        }
        foreach ($opts as $opt) {
          list($key, $value) = explode('=', $opt);
          if (!isset($parsed[$key])) {
            // don't allow params overwrite
            $parsed[$key] = rawurldecode($value);
          }
        }
      }
    }

    return $parsed;
  }

  // }}}

}
