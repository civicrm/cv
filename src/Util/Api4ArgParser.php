<?php

namespace Civi\Cv\Util;
class Api4ArgParser {

  public function parse($args, $defaults = []) {
    $params = $defaults;
    foreach ($args as $arg) {
      if (preg_match('/^(\+|)([a-zA-Z0-9_]+)[=:](.*)/', $arg, $matches)) {

        $mode = $matches[1];
        $key = $matches[2];
        if (strpos('{["\'', $matches[3]{0}) !== FALSE) {
          $value = json_decode($matches[3], 1);
        }
        else {
          $value = $matches[3];
        }

        switch ($mode) {
          case '':
            $params[$key] = $value;
            break;

          case '+':
            $params[$key][] = $value;
            break;
        }

      }
      else {
        throw new \RuntimeException("Unrecognized option format: $arg");
      }
    }
    return $params;
  }

}
