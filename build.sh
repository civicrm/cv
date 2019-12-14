#!/usr/bin/env bash

## Determine the absolute path of the directory with the file
## usage: absdirname <file-path>
function absdirname() {
  pushd $(dirname $0) >> /dev/null
    pwd
  popd >> /dev/null
}

PRJDIR=$(absdirname "$0")
export PATH="$PRJDIR/bin:$PATH"

set -ex
composer install --prefer-dist --no-progress --no-suggest --no-dev
which box
php -d phar.read_only=0 `which box` build -v
