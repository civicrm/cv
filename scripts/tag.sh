#!/usr/bin/env bash
set -ex

TAG=v0.3.45

GUARD=
BRANCH="master"
TAG="$1"
LIB_AUX_DIR=tmp-lib
LIB_AUX_URL='git@github.com:civicrm/cv-lib.git'

$GUARD git checkout "$BRANCH"
$GUARD git pull origin "$BRANCH"
$GUARD git tag "$TAG"
$GUARD git push origin "$BRANCH" "$TAG"

if [ -d "$LIB_AUX_DIR" ]; then
  $GUARD rm -rf "$LIB_AUX_DIR"
fi
$GUARD git clone -b master git@github.com:civicrm/cv-lib.git "$LIB_AUX_DIR"
$GUARD rsync -va --exclude .git --exclude .gitrepo --exclude '*~' --exclude vendor --exclude composer.lock --delete lib/./ "$LIB_AUX_DIR"/./
(cd "$LIB_AUX_DIR" && git add . && git commit -m "Update to $TAG" && git tag "$TAG")

echo "Please inspect the content of \"$LIB_AUX_DIR\""
echo "If it's OK, then run:"
echo
echo "  $ cd $LIB_AUX_DIR"
echo "  $ git push origin master v0.3.45"
