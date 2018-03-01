<?php
namespace Civi\Cv\Util;

class Rand {

  /**
   * Create a unique name.
   *
   * @return string
   *   Random alphanumeric string w/128 bits of entropy.
   */
  public static function createName() {
    if (function_exists('random_bytes')) {
      $hex = bin2hex(random_bytes(16));
      return base_convert($hex, 16, 36);
    }
    elseif (function_exists('openssl_random_pseudo_bytes')) {
      $hex = bin2hex(openssl_random_pseudo_bytes(16));
      return base_convert($hex, 16, 36);
    }
    else {
      $pow16 = pow(2, 16);
      $buf = '';
      for ($i = 0; $i < 8; $i++) {
        $buf .= '-' . mt_rand(0, $pow16);
      }
      return base_convert(md5($buf), 16, 36);
    }
  }

}
