<?php
namespace Civi\Cv;

class Encoder {

  /**
   * Determine the default output mode.
   *
   * @param string $fallback
   *   In case we can't find a default based on a policy, the caller
   *   can suggest their own fallback.
   * @return string
   *   Ex: 'json', 'shell', 'php', 'pretty', 'none'
   */
  public static function getDefaultFormat($fallback = 'json-pretty') {
    $e = getenv('CV_OUTPUT');
    return $e ? $e : $fallback;
  }

  /**
   * Get a list of formats that work with tabular data.
   *
   * @return array
   */
  public static function getTabularFormats() {
    $result = self::getFormats();
    array_unshift($result, 'list');
    array_unshift($result, 'csv');
    array_unshift($result, 'table');
    return $result;
  }

  /**
   * Get a list of formats that work general-purpose data (strings,
   * tables, array-trees, etc).
   *
   * @return array
   */
  public static function getFormats() {
    return array(
      'none',
      'pretty',
      'php',
      'php-data',
      'json-pretty',
      'json-strict',
      'serialize',
      'shell',
    );
  }

  public static function encode($data, $format) {
    switch ($format) {
      case 'none':
        return '';

      case 'pretty':
        return print_r($data, 1);

      case 'php':
        return var_export($data, 1);

      case 'php-data':
        return var_export(static::preferArray($data), 1);

      case 'json-pretty':
        $jsonOptions = (defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0)
          |
          (defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0);
        return json_encode($data, $jsonOptions);

      case 'json':
      case 'json-strict':
        return json_encode($data);

      case 'serialize':
        return serialize($data);

      case 'shell':
        $data = static::preferArray($data);

        if (is_scalar($data)) {
          return escapeshellarg($data);
        }
        elseif (is_array($data)) {
          // FIXME: This works fine for assoc-arrays but not numerical arrays.
          $tree = \Civi\Cv\Util\ArrayUtil::implodeTree('_', $data);
          $buf = '';
          foreach ($tree as $k => $v) {
            $buf .= (sprintf("%s=%s\n", $k, escapeshellarg($v)));
          }
          return $buf;
        }
        else {
          return gettype($data);
        }

      case 'civicrm.settings.php':
        // SpecialFormat: Blurbs for civicrm.settings.php. Used by `setting:get` command.
        $buf = '';
        foreach ($data as $row) {
          if (!isset($row['scope']) || !isset($row['key']) || !isset($row['value'])) {
            throw new \RuntimeException("Cannot format result for civicrm.settings.php. Must have columns: scope,key,value");
          }
          $scope = strpos($row['scope'], 'contact') !== FALSE ? 'contact' : 'domain';
          $buf .= sprintf("\$civicrm_setting[%s][%s] = %s;\n",
            var_export($scope, TRUE),
            var_export($row['key'], TRUE),
            var_export($row['value'], TRUE)
          );
        }
        return $buf;

      default:
        throw new \RuntimeException('Unknown output format');
    }
  }

  private static function preferArray($data) {
    if (is_object($data)) {
      if ($data instanceof \JsonSerializable || $data instanceof \stdClass) {
        $data = json_decode(json_encode($data), TRUE);
      }
    }
    return $data;
  }

}
