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

}
