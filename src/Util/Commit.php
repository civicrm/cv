<?php
namespace Civi\Cv\Util;

class Commit {
  public static function isValid($commit) {
    return preg_match('/^[0-9a-f]+$/', $commit) && 40 == strlen($commit);
  }
}
