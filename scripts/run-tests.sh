#!/bin/bash

## About: Execute all the test suites... with different versions of PHP and MySQL.

## Example usage: ./tests.sh
## Example usage: env DEBUG=2 PHPUNIT=phpunit5 ./tests.sh
## Example usage: killall mysqld; env DEBUG=2 PHPUNIT=phpunit5 ./tests.sh

## TODO: Maybe some JUnit output?

###############################################################################
## Determine the absolute path of the directory with the file
## usage: absdirname <file-path>
function absdirname() {
  pushd $(dirname $0) >> /dev/null
    pwd
  popd >> /dev/null
}

###############################################################################
## Execute the mysqld integration tests using the "ramdisk" style
## usage: test_ramdisk_nix <mysql-pkg-name> <nix-repo-url>
## example: test_ramdisk_nix mysql57 https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz
function test_ramdisk_nix() {
  local pkg="$1"
  local url="$2"
  local name="Ramdisk test ($pkg from $url)"
  echo "[$name] Start"

  ## TIP: If one of these tests fails, then manually start the given daemon with:
  ## $ nix run -f <url> <pkg> -c test-amp-ramdisk amp mysql:start

  if nix run -f "$url" "$pkg" -c test-amp-ramdisk "$PHPUNIT" --group mysqld ; then
    echo "[$name] OK"
  else
    echo "[$name] Fail"
    EXIT_CODE=1
  fi
}

###############################################################################
## Execute the PHP, unit-level tests
## usage: test_phpunit <php-pkg-name> <nix-repo-url> [phpunit-options]
## example: test_phpunit php72 https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz --group foobar
function test_phpunit() {
  local pkg="$1"
  local url="$2"
  shift 2
  local name="Unit tests ($pkg from $url; $@)"
  echo "[$name] Start"
  if nix run -f "$url" "$pkg" -c php $(which "$PHPUNIT") "$@" ; then
    echo "[$name] OK"
  else
    echo "[$name] Fail"
    EXIT_CODE=1
  fi
}

###############################################################################
## Main

SCRIPTDIR=$(absdirname "$0")
PRJDIR=$(dirname "$SCRIPTDIR")
COMPOSER=${COMPOSER:-composer}
PHPUNIT=${PHPUNIT:-phpunit}
PATH="$PRJDIR/bin:$PATH"
export PATH

if [ ! -f "$PRJDIR/bin/amp" ]; then
  echo "Failed to determine amp source dir" 1>&2
  exit 1
fi

EXIT_CODE=0
pushd "$PRJDIR"
  "$COMPOSER" install

  ## Tests are organized into a few groups

  ## (1) The 'unit' tests are lower-level tests for PHP classes/functions. These are executed with multiple versions of PHP.
  test_phpunit     php72   https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz --group unit
  test_phpunit     php74   https://github.com/NixOS/nixpkgs-channels/archive/nixos-20.03.tar.gz --group unit
  test_phpunit     php80   https://github.com/nixos/nixpkgs/archive/594fbfe27905f2fd98d9431038814e497b4fcad1.tar.gz --group unit
  test_phpunit     php81   https://github.com/nixos/nixpkgs/archive/6794a2c3f67a92f374e02c52edf6442b21a52ecb.tar.gz --group unit

  ## (2) The 'mysqld' tests are higher-level integration tests for working with the DBMS. These are executed with multiple versions of MySQL.
  test_ramdisk_nix mysql55 https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz
  test_ramdisk_nix mysql57 https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz
  test_ramdisk_nix mariadb https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz
  test_ramdisk_nix mysql80 https://github.com/NixOS/nixpkgs/archive/20.09.tar.gz
popd
exit $EXIT_CODE
