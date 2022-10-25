<?php
namespace Civi\Cv\Util;

class SettingCodec {

  /**
   * @param array $meta
   *   All setting metadata (for given context).
   * @param string $field
   *   Name of the field.
   * @return array
   *   Pair of callables: [$encode, $decode]
   */
  public static function codec(array $meta, string $field) {
    // There are a handful of  settings with a secondary/nested encoding.
    $encode = function($value) use ($meta, $field) {
      if (isset($value) && !empty($meta[$field]['serialize'])) {
        return \CRM_Core_DAO::serializeField($value, $meta[$field]['serialize']);
      }
      return $value;
    };
    $decode = function($value) use ($meta, $field) {
      if (isset($value) && !empty($meta[$field]['serialize'])) {
        return \CRM_Core_DAO::unSerializeField($value, $meta[$field]['serialize']);
      }
      return $value;
    };
    return [$encode, $decode];
  }

}
