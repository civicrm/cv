<?php

namespace Civi\Cv\Util;

class Json {

  /**
   * @param mixed $value
   * @param string $format
   *   "raw": Similar to PHP default
   *   "flat": Use a single line. Avoid extraneous escaping. (Suitable for JSON files but not HTML-JSON.)
   *   "pretty": Use many lines with indentation. Avoid extraneous escaping. (Suitable for JSON files but not HTML-JSON.)
   * @param mixed $onFailure
   *   false: Legacy behavior, return FALSE.
   *   null: New behavior, return NULL. Easier to string with `??` operator.
   *   "throw": Generate an exception.
   * @return string|false
   */
  public static function encode($value, string $format, $onFailure = 'throw') {
    $flags = [
      'raw' => 0,
      'flat' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
      'pretty' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ];
    if (!isset($flags[$format])) {
      throw new \LogicException("Invalid JSON format: $format");
    }

    $options = JSON_THROW_ON_ERROR | $flags[$format];
    try {
      return json_encode($value, $options);
    }
    catch (\JsonException $e) {
      if ($onFailure === FALSE || $onFailure === NULL) {
        fprintf(STDERR, "Failed to encode JSON: %s\n", $e->getMessage());
        return $onFailure;
      }
      throw $e;
    }
  }

}
