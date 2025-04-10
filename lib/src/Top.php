<?php

namespace Civi\Cv;

class Top {

  /**
   * @var string|null
   */
  private static $prefixRegex;

  /**
   * Call a top-level function or method
   *
   * @param string|string[] $callable
   *   Ex: 'drupal_bootstrap'
   *   Ex: '\drupal_bootstrap'
   *   Ex: ['Drupal', 'service']
   *   Note: All symbols are implicitly relative to top-level namespace.
   * @param mixed[] $args
   * @return mixed
   */
  public static function call($callable, ...$args) {
    $callable = static::symbol($callable);
    return call_user_func_array($callable, $args);
  }

  /**
   * Instantiate an object (based on the top-level classname).
   *
   * @param string $class
   *   Ex: 'Symfony\Contracts\EventDispatcher\EventDispatcher'
   *   Ex: '\Symfony\Contracts\EventDispatcher\EventDispatcher'
   *   Note: All symbols are implicitly relative to top-level namespace.
   * @param mixed[] $args
   * @return object
   */
  public static function create($class, ...$args) {
    $class = static::symbol($class);
    return new $class(...$args);
  }

  /**
   * Evaluate a symbol, returning the top-level version of that symbol.
   *
   * @param string|string[] $symbol
   *   Ex: 'Drupal', '\Symfony', ['\Drupal', 'service'], 'Drupal::service'
   * @return string|string[]
   *   The same symbol, ut without any php-scoper prefixes.
   */
  public static function symbol($symbol) {
    if (is_string($symbol)) {
      // Translate function or class
      if (static::$prefixRegex === NULL) {
        static::$prefixRegex = static::createPrefixRegex();
      }
      $result = preg_replace(static::$prefixRegex, '\\', $symbol);
      if ($result[0] !== '\\') {
        $result = '\\' . $result;
      }
      return $result;
    }
    elseif (is_array($symbol)) {
      // Translate class
      $symbol[0] = static::symbol($symbol[0]);
      return $symbol;
    }
    else {
      return $symbol;
    }
  }

  /**
   * Build a string to match the php-scoper prefix.
   *
   * @return string
   */
  protected static function createPrefixRegex(): string {
    $parts = explode('\\', \Path\To\Dummy::class);
    $topNS = $parts[0];

    // Are we running in a scoped PHAR?
    if ($topNS[0] === '_' || substr($topNS, -4) === 'Phar' || substr($topNS, -4) === 'phar') {
      // In `box`+`php-scoper`, default prefix is dynamic value like '_HumbugXYZ'.
      // Most of my projects use an explicit prefix like 'MyAppPhar'
      return ';^\\\?' . preg_quote($parts[0], ';') . '\\\;';
    }
    else {
      return ';^\\\;';
    }
  }

}
