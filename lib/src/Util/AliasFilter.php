<?php

namespace Civi\Cv\Util;

class AliasFilter {

  /**
   * Find an option like `cv @mysite ext:list`. Convert the `@mysite`
   * notation to `--site-alias=mysite`.
   *
   * @param array $input
   * @return array
   */
  public static function filter(array $input): array {
    $todo = $input;
    $result = [];
    $result[] = array_shift($todo);
    while (count($todo) > 0) {
      $value = array_shift($todo);
      if ($value[0] === '@') {
        $result[] = '--site-alias=' . substr($value, 1);
        $result = array_merge($result, $todo);
        $todo = [];
      }
      else {
        $result[] = $value;
      }
    }
    return $result;
  }

}
