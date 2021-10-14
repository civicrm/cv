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
      // Ex: 'foo=bar', 'fo.oo=bar', 'fo:oo=bar'
      elseif (preg_match('/^([a-zA-Z0-9_:\.]+)=(.*)/', $arg, $matches)) {
        list (, $key, $value) = $matches;
        $params[$key] = $this->parseValueExpr($value);
      }
      // Ex: '+w', '+where'
      elseif (preg_match('/^\+([a-zA-Z0-9_]+)$/', $arg, $matches)) {
        $state = $matches[1];
      }
      // Ex: '+l=2', '+l:2'
      elseif (preg_match('/^\+([a-zA-Z0-9_]+)[:=](.*)/', $arg, $matches)) {
        list (, $key, $expr) = $matches;
        $this->applyOption($params, $key, $expr);
      }
      // Ex: '{"foo": "bar"}'
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
    if (strpos('{["\'', $expr[0]) !== FALSE) {
      return $this->parseJsonNoisily($expr);
    }
    else {
      return $expr;
    }
  }

  /**
   * @param $params
   * @param $key
   * @param $expr
   * @return mixed
   */
  protected function applyOption(&$params, $key, $expr) {
    $aliases = [
      's' => 'select',
      'w' => 'where',
      'o' => 'orderBy',
      'l' => 'limit',
      'v' => 'values',
      'value' => 'values',
    ];
    $key = isset($aliases[$key]) ? $aliases[$key] : $key;

    switch ($key) {
      case 'select':
        self::mergeInto($params, $key, array_map([
          $this,
          'parseValueExpr',
        ], preg_split('/[, ]/', $expr)));
        break;

      case 'where':
        self::appendInto($params, $key, $this->parseWhere($expr));
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

      case 'values':
        self::mergeInto($params, $key, $this->parseAssignment($expr));
        break;

      default:
        throw new \RuntimeException("Unrecognized option: +$key");
    }
  }

  public function parseAssignment($expr) {
    if (preg_match('/^([a-zA-Z0-9_:\.]+)\s*=\s*(.*)$/', $expr, $matches)) {
      return [$matches[1] => $this->parseValueExpr($matches[2])];
    }
    else {
      throw new \RuntimeException("Error parsing \"value\": $expr");
    }
  }

  public function parseWhere($expr) {
    if (preg_match('/^([a-zA-Z0-9_:\.]+)\s*(\<=|\>=|=|!=|\<|\>|IS NULL|IS NOT NULL|LIKE|NOT LIKE|IN|NOT IN)\s*(.*)$/i', $expr, $matches)) {
      if (!empty($matches[3])) {
        return [$matches[1], strtoupper(trim($matches[2])), $this->parseValueExpr(trim($matches[3]))];
      }
      else {
        return [$matches[1], strtoupper($matches[2])];
      }
    }
    else {
      throw new \RuntimeException("Error parsing \"where\": $expr");
    }
  }

}
