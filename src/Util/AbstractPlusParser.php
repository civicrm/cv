<?php

namespace Civi\Cv\Util;

/**
 * Some cv commands allow two dimensions of parameters -- the dash parameters
 * (`-f`, `--force`) are instructions for how to process the command-line request.
 * The plus parameters (`+s foo`, `+select foo`) are shorthand for data updates.
 *
 * This is a base-class for separating out the plus parameters.
 */
abstract class AbstractPlusParser {

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
        [, $key, $value] = $matches;
        $params[$key] = $this->parseValueExpr($value);
      }
      // Ex: '+w', '+where'
      elseif (preg_match('/^\+([a-zA-Z0-9_]+)$/', $arg, $matches)) {
        $state = $matches[1];
      }
      // Ex: '+l=2', '+l:2'
      elseif (preg_match('/^\+([a-zA-Z0-9_]+)[:=](.*)/', $arg, $matches)) {
        [, $key, $expr] = $matches;
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
    if ($expr !== '' && strpos('{["\'', $expr[0]) !== FALSE) {
      return $this->parseJsonNoisily($expr);
    }
    else {
      return $expr;
    }
  }

  /**
   * @param array $params
   * @param string $type
   *   The name of the plus parameter.
   *   Ex: "+s foo" ==> "s"
   *   Ex: "+where foo=bar" ==> "where"
   * @param string $expr
   *   Ex: "+s foo" ==> "foo"
   *   Ex: "+where foo=bar" ==> "foo=bar"
   *
   * @return mixed
   */
  abstract protected function applyOption(array &$params, string $type, string $expr): void;

  public function parseAssignment($expr) {
    if (preg_match('/^([a-zA-Z0-9_:\.]+)\s*=\s*(.*)$/', $expr, $matches)) {
      return [$matches[1] => $this->parseValueExpr($matches[2])];
    }
    else {
      throw new \RuntimeException("Error parsing \"value\": $expr");
    }
  }

}
