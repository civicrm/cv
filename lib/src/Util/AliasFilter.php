<?php

namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\ArgvInput;

class AliasFilter {

  /**
   * Find an option like `cv @mysite ext:list`. Convert the `@mysite`
   * notation to `--site-alias=mysite`.
   *
   * @param array $argv
   * @return array
   */
  public static function filter(array $argv): array {
    if (!preg_grep('/^@/', $argv)) {
      return $argv;
    }

    $input = new ArgvInput($argv);
    $firstArg = $input->getFirstArgument();
    if ($firstArg[0] === '@') {
      return static::replace($argv, $firstArg, '--site-alias=' . substr($firstArg, 1));
    }

    return $argv;
  }

  private static function replace(array $original, $old, $new) {
    $pos = array_search($old, $original, TRUE);
    $original[$pos] = $new;
    return $original;
  }

}
