#!/bin/bash

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

SCRIPTDIR=$(absdirname "$0")
PRJDIR=$(dirname "$SCRIPTDIR")

###############################################################################
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
if [ ! -f "$PRJDIR/bin/amp" ]; then
  echo "Failed to determine amp source dir" 1>&2
  exit 1
fi

COMPOSER=${COMPOSER:-composer}
PHPUNIT=${PHPUNIT:-phpunit}
PATH="$PRJDIR/bin:$PATH"
export PATH
EXIT_CODE=0

pushd "$PRJDIR"
  "$COMPOSER" install
  test_phpunit     php56   https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.03.tar.gz --group unit
  test_phpunit     php72   https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz --group unit
  test_ramdisk_nix mysql55 https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz
  test_ramdisk_nix mysql57 https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz
  test_ramdisk_nix mariadb https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz
  test_ramdisk_nix mysql80 https://github.com/NixOS/nixpkgs-channels/archive/d5291756487d70bc336e33512a9baf9fa1788faf.tar.gz
popd

exit $EXIT_CODE
