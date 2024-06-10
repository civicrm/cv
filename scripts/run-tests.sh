#!/bin/bash
{ # https://stackoverflow.com/a/21100710

## Run PHPUnit tests. This is a small wrapper for `phpunit` which does some setup for the environment.

set -e

#####################################################################
function usage() {
  echo "usage: ./scripts/run-tests.sh [--phar|--src] [...phpunit-args...]"
  echo
  echo "Options:"
  echo "  --phar (Compile the PHAR and run tests against that)"
  echo "  --src (Run tests against the local source tree)"
  echo
  echo "Environment":
  echo "  CV_TYPE=phar     (Compile the PHAR and run tests aginst that)"
  echo "  CV_TYPE=src      (Run tests against the local source tree)"
  echo "  PHPUNIT=phpunit9 (Specify PHPUnit version)"
  echo "  SUITE=cv-std     Run the standard suite"
}

#####################################################################
function main() {
  CV_PROJECT="$PWD"

  ## Didn't set a workspace? Educated guess...
  if [ -z "$CV_TEST_BUILD" -a -d "$CIVIBUILD_HOME/dmaster/web" ]; then
    export CV_TEST_BUILD="$CIVIBUILD_HOME/dmaster/web"
    echo "Inferred CV_TEST_BUILD=$CV_TEST_BUILD"
  fi
  if [ -z "$CV_TEST_BUILD" ]; then
    echo "Missing env var: CV_TEST_BUILD"
    exit 1
  fi
  if [ -z "$PHPUNIT" ]; then
    PHPUNIT=phpunit9
  fi


  local passthru=()
  local compile=
  local hasSuite

  case "$CV_TYPE" in
    phar)
      CV_TEST_BINARY="$CV_PROJECT/bin/cv.phar"
      compile=1
      ;;
    src)
      CV_TEST_BINARY="$CV_PROJECT/bin/cv"
      compile=
      ;;
  esac

  for arg in "$@" ; do
    case "$arg" in
      --phar)
        CV_TEST_BINARY="$CV_PROJECT/bin/cv.phar"
        compile=1
        ;;
      --src)
        CV_TEST_BINARY="$CV_PROJECT/bin/cv"
        compile=
        ;;
      --tap)
        passthru+=("--debug")
        ;;
      --debug|--stop-on-failure|--stop-on-error)
        passthru+=("$arg")
        ;;
      *)
        passthru+=("$arg")
        hasSuite=1
        ;;
    esac
  done

  if [ -n "$SUITE" ]; then
    case "$SUITE" in
      "cv-std") passthru+=("--group" "std") ; hasSuite=1 ; ;;
      "cv-installer") passthru+=("--group" "installer") ; hasSuite=1 ; ;;
      *) echo 1>&2  "Unrecognized SUITE=$SUITE" ; hasSuite=1 ; ;;
    esac
  fi

  if [ -z "$hasSuite" ]; then
    usage 1>&2
    exit 1
  fi

  if [ -n "$compile" ]; then
    ./scripts/build.sh
  fi
  (cd "$CV_TEST_BUILD" && XDEBUG_MODE=off civibuild restore)

  export CV_TEST_BINARY
  "$PHPUNIT" "${passthru[@]}"
}

################################################
main "$@"
}
