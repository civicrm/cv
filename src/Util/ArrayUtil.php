<?php
namespace Civi\Cv\Util;

class ArrayUtil {

  /**
   * Evaluate each item in the list. Concatenate the results.
   *
   * @param array $items
   *   Ex: ["a", "b"]
   * @param callable $filter
   *   Ex: fn($item) => "<li>$item</li>"
   * @param string $delimiter
   * @return string
   *   Ex: "<li>a</li><li>b</li>"
   */
  public static function mapImplode(array $items, callable $filter, string $delimiter = ''): string {
    return implode($delimiter, array_map($filter, $items));
  }

  /**
   * Evaluate each item in the list.
   *
   * Similar to array_map(), but the order matches the other variants of map.
   *
   * @param array $array
   * @param callable $func
   *   function($value): mixed
   * @return array
   */
  public static function map(array $array, callable $func): array {
    return array_map($func, $array);
  }

  /**
   * Evaluate each item in the list.
   *
   * Similar to array_map(), but guaranteed to provide two inputs ($key,$value).
   *
   * @param array $array
   * @param callable $func
   *   function($key, $value): mixed
   * @return array
   */
  public static function mapKV(array $array, callable $func): array {
    return array_map($func, array_keys($array), array_values($array));
  }

  /**
   * Evaluate each item in the list. Concatenate the results.
   *
   * @param array $items
   *   Ex: ["Ray" => "A drop of golden sun"]
   * @param callable $filter
   *   Ex: fn($key, $value) => "<dt>$key</dt><dd>$value</dd>"
   * @param string $delimiter
   * @return string
   *   Ex: "<dt>Ray</dt><dd>A drop of golden sun</dd>"
   */
  public static function mapImplodeKV(array $items, callable $filter, string $delimiter = '') {
    return implode($delimiter, static::mapKV($items, $filter));
  }

  public static function collect($array, $index) {
    $result = array();
    foreach ($array as $item) {
      if (isset($item[$index])) {
        $result[] = $item[$index];
      }
    }
    return $result;
  }

  public static function implodeTree($delim, &$arr) {
    $result = array();
    foreach ($arr as $key => &$value) {
      if (is_array($value)) {
        $temp = &self::implodeTree($delim, $value);
        foreach ($temp as $key2 => $value2) {
          $result[$key . $delim . $key2] = $value2;
        }
      }
      else {
        $result[$key] = $value;
      }
    }
    return $result;
  }

  /**
   * Set a single value in an array tree.
   *
   * @param array $arr
   *   Ex: array('foo'=>array('bar'=>123)).
   * @param array $pathParts
   *   Ex: array('foo',bar').
   * @param $value
   *   Ex: 456.
   */
  public static function pathSet(&$arr, $pathParts, $value) {
    $r = &$arr;
    $last = array_pop($pathParts);
    foreach ($pathParts as $part) {
      if (!isset($r[$part])) {
        $r[$part] = array();
      }
      $r = &$r[$part];
    }
    $r[$last] = $value;
  }

  /**
   * Convert an associative array to a list of records with properties.
   *
   * @param array $records
   *   Ex: ['red' => '#f00']
   * @param string|int $keyProp
   *   Name of the new property in the result record.
   *   Ex: 'key', 'color-name'
   * @param string|int $valueProp
   *   Name of the new property in the result record.
   *   Ex: 'value', 'color-hex'
   * @return array
   *   Ex: [['key' => 'red', 'value' => '#f00']]
   *   Ex: [['color-name' => 'red', 'color-hex' => '#f00']]
   */
  public static function convertKeyValueRecord($records, $keyProp = 0, $valueProp = 1) {
    $result = [];
    foreach ($records as $key => $value) {
      $result[] = [$keyProp => $key, $valueProp => $value];
    }
    return $result;
  }

  /**
   * Convert a list of records from associative-arrays to numeric-arrays.
   *
   * @param array $records
   *   A list of records. Each one is associative array (dictionary/hashmap).
   *   Ex: $rows[0] = array('color' => 'red', 'length' => 5);
   * @param array $columns
   *   A list of columns.
   *   Ex: array(0 => 'color', 1 => 'length').
   * @param array $defaults
   *   Default values for any columns that are missing.
   *   NULL if omitted.
   *   Ex: array(0 => 'transparent', 1 => '0 ft')
   * @return array
   *   A list of records. Each one is a numerically indexed array.
   *   Ex: $rows[0] = array(0 => 'red', 1 => 'length')
   */
  public static function convertAssocToNum($records, $columns, $defaults = []) {
    $result = array();
    foreach ($records as $k => $oldRow) {
      $newRow = array();
      foreach ($columns as $newKey => $oldKey) {
        $newRow[$newKey] = $oldRow[$oldKey] ?? $defaults[$newKey] ?? NULL;
      }
      $result[$k] = $newRow;
    }
    return $result;
  }

  /**
   * Filter down to the whitelisted column in a set of records.
   *
   * @param array $records
   *   A list of records. Each one is associative array (dictionary/hashmap).
   *   Ex: $rows[0] = array('color' => 'red', 'length' => 5);
   * @param array $columns
   *   A list of columns.
   *   Ex: array('length').
   * @return array
   *   A list of records. Each one is a numerically indexed array.
   *   Ex: $rows[0] = array('length' => 5)
   */
  public static function filterColumns($records, $columns) {
    $result = array();
    foreach ($records as $k => $oldRow) {
      $newRow = array();
      foreach ($columns as $key) {
        if (array_key_exists($key, $oldRow)) {
          $newRow[$key] = $oldRow[$key];
        }
      }
      $result[$k] = $newRow;
    }
    return $result;
  }

  /**
   * Autodetect the columns headers in a matrix.
   *
   * @param array $records
   * @return array|null
   */
  public static function findColumns($records) {
    foreach ($records as $record) {
      $columns = array_keys($record);
      if ($columns) {
        return $columns;
      }
    }
    return NULL;
  }

  /**
   * Given some requested columns, determine the actual set of columns (available for this data).
   *
   * @param string[]|null $columns
   *   List of requested columns. May include wildcard '*'.
   * @param array $records
   *   List of records
   * @return array
   *   The concrete list of column-names. (Wildcard replaced by actual values.)
   */
  public static function resolveColumns(?array $columns, array $records): array {
    if (is_array($columns) && in_array('*', $columns)) {
      $columns = NULL;
    }
    return $columns ?: ArrayUtil::findColumns($records);
  }

  public static function sortColumns(array $records, ?array $columns): array {
    $columns = static::resolveColumns($columns, $records);
    usort($records, function ($a, $b) use ($columns) {
      foreach ($columns as $col) {
        if ($a[$col] < $b[$col]) {
          return -1;
        }
        if ($a[$col] > $b[$col]) {
          return 1;
        }
      }

      return 0;
    });

    return $records;
  }

  /**
   * Grab the first non-empty-ish value.
   *
   * @param array $values
   * @param callable|NULL $filter
   * @return mixed|NULL
   */
  public static function pickFirst($values, $filter = NULL) {
    foreach ($values as $value) {
      if (($filter !== NULL && $filter($value)) || ($filter === NULL && $value)) {
        return $value;
      }
    }
    return NULL;
  }

}
