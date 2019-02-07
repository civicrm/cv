<?php

namespace Civi\Cv\Util;
class Api4ArgParser {

  public function parse($args, $defaults = []) {
    $state = '_TOP_';
    $params = $defaults;
    foreach ($args as $arg) {
      if ($state !== '_TOP_') {
        $this->applyOption($params, $state, $arg);
        $state = '_TOP_';
      }
      elseif (preg_match('/^([a-zA-Z0-9_]+)=(.*)/', $arg, $matches)) {
        list (, $key, $value) = $matches;
        $params[$key] = $this->parseValueExpr($value);
      }
      elseif (preg_match('/^\+([a-zA-Z0-9_]+)$/', $arg, $matches)) {
        $state = $matches[1];
      }
      elseif (preg_match('/^\+([a-zA-Z0-9_]+)[:=](.*)/', $arg, $matches)) {
        list (, $key, $expr) = $matches;
        $this->applyOption($params, $key, $expr);
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

  protected static function mergeInto(&$params, $key, $values) {
    if (!isset($params[$key])) {
      $params[$key] = [];
    }
    $params[$key] = array_merge($params[$key], $values);
  }

  protected static function appendInto(&$params, $key, $values) {
    if (!isset($params[$key])) {
      $params[$key] = [];
    }
    $params[$key][] = $values;
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

  /**
   * @param $params
   * @param $key
   * @param $expr
   * @return mixed
   */
  protected function applyOption(&$params, $key, $expr) {
    $aliases = ['s' => 'select', 'w' => 'where', 'o' => 'orderBy', 'l' => 'limit'];
    $key = isset($aliases[$key]) ? $aliases[$key] : $key;

    switch ($key) {
      case 'select':
        self::mergeInto($params, $key, array_map([
          $this,
          'parseValueExpr'
        ], preg_split('/[, ]/', $expr)));
        break;

      case 'where':
        self::appendInto($params, $key, array_map([
          $this,
          'parseValueExpr'
        ], $this->explode($expr)));
        break;

      case 'orderBy':
        $keyOrderPairs = explode(',', $expr);
        foreach ($keyOrderPairs as $keyOrderPair) {
          $keyOrderPair = explode(' ', trim($keyOrderPair));
          $sortKey = $keyOrderPair[0];
          $sortOrder = isset($keyOrderPair[1]) ? strtoupper($keyOrderPair[1]) : 'ASC';
          $params[$key][$sortKey] = $sortOrder;
        }
        break;

      case 'limit':
        if (strpos($expr, '@') !== FALSE) {
          list ($limit, $offset) = explode('@', $expr);
          $params['limit'] = (int) $limit;
          $params['offset'] = (int) $offset;
        }
        else {
          $params['limit'] = (int) $expr;
        }
        break;

      default:
        throw new \RuntimeException("Unrecognized option: +$key");
    }
  }

}
