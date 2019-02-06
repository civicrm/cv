<?php

namespace Civi\Cv\Util;
class Api4ArgParser {

  public function parse($args, $defaults = []) {
    $params = $defaults;
    foreach ($args as $arg) {
      if (preg_match('/^(\+|)([a-zA-Z0-9_]+)(=|:)(.*)/', $arg, $matches)) {

        $mode = $matches[1];
        $key = $matches[2];
        switch ($matches[3]) {
          case '=':
            $value = $this->parseValueExpr($matches[4]);
            break;

          case ':':
            // $value = $this->explode($matches[4]);
            $value = array_map([$this, 'parseValueExpr'], $this->explode($matches[4]));
            break;

          default:
            throw new \RuntimeException("Unrecognized operator");
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

  /**
   * @param $expr
   * @return mixed
   */
  protected function parseValueExpr($expr) {
    if (strpos('{["\'', $expr{0}) !== FALSE) {
      return $this->parseJsonNoisily($expr);
    }
    else {
      return $expr;
    }
  }

  public function explode($expr) {
    $items = [];
    $buf = '';
    $delim = ' ';
    $state = 'top';
    $len = strlen($expr);
    for ($i = 0; $i < $len; $i++) {
      if ($state === 'top' && $expr{$i} === $delim) {
        $items[] = $buf;
        $buf = '';
      }
      elseif ($state === 'top' && $expr{$i} === '"') {
        $state = 'dbl';
        $buf .= $expr{$i};
      }
      elseif ($state === 'top') {
        $buf .= $expr{$i};
      }
      elseif ($state === 'dbl' && $expr{$i} === '"') {
        $state = 'top';
        $buf .= $expr{$i};
      }
      elseif ($state === 'dbl' && $expr{$i} !== '"') {
        $buf .= $expr{$i};
      }
      else {
        throw new \RuntimeException("Error parsing: $expr (state=$state, i=$i, ch={$expr{$i}})");
      }
    }

    if (!empty($buf)) {
      $items[] = $buf;
    }
    return $items;
  }

}
