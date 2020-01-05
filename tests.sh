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

PRJDIR=$(absdirname "$0")

###############################################################################
## usage: test_ramdisk_nix <nix-pkg-url> <nix-pkg-name>
## example: test_ramdisk_nix https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz mysql57
function test_ramdisk_nix() {
  local name="Ramdisk test ($2 from $1)"
  echo "[$name] Start"

  ## TIP: If one of these tests fails, then manually start the given daemon with:
  ## $ nix run -f <url> <pkg> -c test-amp-ramdisk amp mysql:start

  if nix run -f "$1" "$2" -c test-amp-ramdisk "$PHPUNIT" --group mysqld ; then
    echo "[$name] OK"
  else
    echo "[$name] Fail"
    EXIT_CODE=1
  fi
}

function test_phpunit() {
  local name="Unit tests"
  echo "[$name] Start"
  if $PHPUNIT "$@" ; then
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

PHPUNIT=${PHPUNIT:-phpunit}
PATH="$PRJDIR/bin:$PATH"
export PATH
EXIT_CODE=0

test_phpunit --group unit
test_ramdisk_nix https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz                                mysql55
test_ramdisk_nix https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz                                mysql57
test_ramdisk_nix https://github.com/NixOS/nixpkgs-channels/archive/nixos-18.09.tar.gz                                mariadb
test_ramdisk_nix https://github.com/NixOS/nixpkgs-channels/archive/d5291756487d70bc336e33512a9baf9fa1788faf.tar.gz   mysql80

exit $EXIT_CODE
