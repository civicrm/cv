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
          $value = $this->parseJsonNoisily($matches[3]);
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
      elseif (preg_match('/^\{.*\}$/', $arg)) {
        $params = array_merge($params, $this->parseJsonNoisily($arg));
      }
      else {
        throw new \RuntimeException("Unrecognized option format: $arg");
      }
    }
    return $params;
  }

  /**
   * @param string $arg
   * @return mixed
   */
  protected function parseJsonNoisily($arg) {
    $values = json_decode($arg, 1);
    if ($values === NULL) {
      throw new \RuntimeException("Failed to parse JSON: $values");
    }
    return $values;
  }

}
