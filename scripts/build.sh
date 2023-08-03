#!/usr/bin/env bash

## Determine the absolute path of the directory with the file
## usage: absdirname <file-path>
function absdirname() {
  pushd $(dirname $0) >> /dev/null
    pwd
  popd >> /dev/null
}

SCRDIR=$(absdirname "$0")
PRJDIR=$(dirname "$SCRDIR")
OUTFILE="$PRJDIR/bin/cv.phar"
set -e

pushd "$PRJDIR" >> /dev/null
  composer install --prefer-dist --no-progress --no-suggest --no-dev
  box compile -v
  php scripts/check-phar.php "$OUTFILE"
popd >> /dev/null
