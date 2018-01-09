<?php
namespace Civi\Cv\Util;
class ArrayUtil {
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
   * Convert a list of records from associative-arrays to numeric-arrays.
   *
   * @param array $records
   *   A list of records. Each one is associative array (dictionary/hashmap).
   *   Ex: $rows[0] = array('color' => 'red', 'length' => 5);
   * @param array $columns
   *   A list of columns.
   *   Ex: array(0 => 'color', 1 => 'length').
   * @return array
   *   A list of records. Each one is a numerically indexed array.
   *   Ex: $rows[0] = array(0 => 'red', 1 => 'length')
   */
  public static function convertAssocToNum($records, $columns) {
    $result = array();
    foreach ($records as $k => $oldRow) {
      $newRow = array();
      foreach ($columns as $newKey => $oldKey) {
        $newRow[$newKey] = $oldRow[$oldKey];
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
   * Grab the first non-empty-ish value.
   *
   * @param array $values
   * @return mixed|NULL
   */
  public static function pickFirst($values) {
    foreach ($values as $value) {
      if ($value) {
        return $value;
      }
    }
    return NULL;
  }

}
