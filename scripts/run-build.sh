#!/usr/bin/env bash

## About: Generate the "amp.phar" file.

## Determine the absolute path of the directory with the file
## usage: absdirname <file-path>
function absdirname() {
  pushd $(dirname $0) >> /dev/null
    pwd
  popd >> /dev/null
}

SCRIPTDIR=$(absdirname "$0")
PRJDIR=$(dirname "$SCRIPTDIR")

set -ex
pushd "$PRJDIR"
  composer install --prefer-dist --no-progress --no-suggest --no-dev
  which box
  php -d phar.readonly=0 `which box` build -v
popd
