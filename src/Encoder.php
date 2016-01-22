<?php
namespace Civi\Cv;

class Encoder {

  /**
   * Determine the default output mode.
   *
   * @return string
   *   Ex: 'json', 'shell', 'php', 'pretty', 'none'
   */
  public static function getDefaultFormat() {
    $e = getenv('CV_OUTPUT');
    return $e ? $e : 'json-pretty';
  }

  public static function getFormats() {
    return array(
      'none',
      'pretty',
      'php',
      'json-pretty',
      'json-strict',
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

      case 'json-pretty':
        $jsonOptions = (defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0)
          |
          (defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES : 0);
        return json_encode($data, $jsonOptions);

      case 'json':
      case 'json-strict':
        return json_encode($data);

      case 'shell':
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

      default:
        throw new \RuntimeException('Unknown output format');
    }
  }

}