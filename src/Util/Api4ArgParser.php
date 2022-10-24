<?php

namespace Civi\Cv\Util;

class Api4ArgParser extends AbstractPlusParser {

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

}
