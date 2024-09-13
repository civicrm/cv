<?php

namespace Civi\Cv\Util;

class Api4ArgParser extends AbstractPlusParser {

  /**
   * @var string
   */
  private $operators;

  public function __construct() {
    if (is_callable(['\Civi\Api4\Utils\CoreUtil', 'getOperators'])) {
      // 5.30+
      $this->operators = implode('|', ArrayUtil::map(\Civi\Api4\Utils\CoreUtil::getOperators(), function($op) {
        return preg_quote($op, '/');
      }));
    }
    else {
      $this->operators = '\<=|\>=|=|!=|\<|\>|IS NULL|IS NOT NULL|IS EMPTY|IS NOT EMPTY|LIKE|NOT LIKE|IN|NOT IN|CONTAINS|NOT CONTAINS|REGEXP|NOT REGEXP|REGEXP BINARY|NOT REGEXP BINARY';
    }
  }

  /**
   * @param array $params
   * @param string $type
   * @param string $expr
   *
   * @return mixed
   */
  protected function applyOption(array &$params, string $type, string $expr): void {
    $aliases = [
      's' => 'select',
      'w' => 'where',
      'o' => 'orderBy',
      'l' => 'limit',
      'v' => 'values',
      'value' => 'values',
    ];
    $type = $aliases[$type] ?? $type;

    switch ($type) {
      case 'select':
        self::mergeInto($params, $type, array_map([
          $this,
          'parseValueExpr',
        ], preg_split('/[, ]/', $expr)));
        break;

      case 'where':
        self::appendInto($params, $type, $this->parseWhere($expr));
        break;

      case 'orderBy':
        $keyOrderPairs = explode(',', $expr);
        foreach ($keyOrderPairs as $keyOrderPair) {
          $keyOrderPair = explode(' ', trim($keyOrderPair));
          $sortKey = $keyOrderPair[0];
          $sortOrder = isset($keyOrderPair[1]) ? strtoupper($keyOrderPair[1]) : 'ASC';
          $params[$type][$sortKey] = $sortOrder;
        }
        break;

      case 'limit':
        if (strpos($expr, '@') !== FALSE) {
          [$limit, $offset] = explode('@', $expr);
          $params['limit'] = (int) $limit;
          $params['offset'] = (int) $offset;
        }
        else {
          $params['limit'] = (int) $expr;
        }
        break;

      case 'values':
        self::mergeInto($params, $type, $this->parseAssignment($expr));
        break;

      default:
        throw new \RuntimeException("Unrecognized option: +$type");
    }
  }

  public function parseWhere($expr) {
    if (preg_match('/^([a-zA-Z0-9_:\.]+)\s*(' . $this->operators . ')\s*(.*)$/i', $expr, $matches)) {
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
