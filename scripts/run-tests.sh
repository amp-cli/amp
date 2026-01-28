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
## usage: test_ramdisk_nix <profile>
## example: test_ramdisk_nix php73m57
function test_ramdisk_nix() {
  local profile="$1"
  local name="Ramdisk test ($profile)"
  echo "[$name] Start"

  ## TIP: If one of these tests fails, then manually start the given daemon with:
  ## $ nix-shell nix/shells.nix -A <profile> --run "test-amp-ramdisk amp mysql:start"

  if nix-shell nix/shells.nix -A "$profile" --run "test-amp-ramdisk phpunit9 --group mysqld" ; then
    echo "[$name] OK"
  else
    echo "[$name] Fail"
    EXIT_CODE=1
  fi
}

###############################################################################
## Execute the PHP, unit-level tests
## usage: test_phpunit <PROFILE> [phpunit-options]
## example: test_phpunit php72m80 --group foobar
function test_phpunit() {
  local profile="$1"
  shift
  local cmd="phpunit9 $@"
  local name="Unit tests ($cmd)"
  echo "[$name] Start"

  if nix-shell nix/shells.nix -A "$profile" --run "$cmd" ; then
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
  nix-shell nix/shells.nix -A php74m80 --run "composer install"

  ## Tests are organized into a few groups

  if [ ! -d tmp ]; then
    mkdir tmp
  fi

  ## (1) The 'unit' tests are lower-level tests for PHP classes/functions. These are executed with multiple versions of PHP.
  test_phpunit     php74m80   --group unit        | tee "tmp/unit-php74.txt"
  test_phpunit     php80m80   --group unit        | tee "tmp/unit-php80.txt"
  test_phpunit     php84m80   --group unit        | tee "tmp/unit-php84.txt"
  test_phpunit     php85m80   --group unit        | tee "tmp/unit-php84.txt"

  ## (2) The 'mysqld' tests are higher-level integration tests for working with the DBMS. These are executed with multiple versions of MySQL.
  DB_PROFILES="$(echo php{74,83,85}m{57,80,84,90})"
  DB_PROFILES=$( echo "$DB_PROFILES" | sed s/php73m90// ) ## PHP<=7.3 and MySQL>=9.0 disagree about auth (mysql_native_password vs caching_sha2_password)
  ## DB_PROFILES="php73m57 php83m90"   ## Or just list some specific ones
  for PROF in $DB_PROFILES; do
    test_ramdisk_nix "$PROF" | tee "tmp/ramdisk-$PROF.txt"
  done
popd
exit $EXIT_CODE
