<?php

namespace Civi\Cv\Util;

class SettingArgParser extends AbstractPlusParser {

  /**
   * @var \Civi\Core\SettingsBag
   */
  protected $settingsBag;

  /**
   * @var array
   */
  protected $settingsMeta;

  protected $pathDelimiter = '.';

  /**
   * @param \Civi\Core\SettingsBag $settingsBag
   * @param array $settingsMeta
   *   List of fields supported by this settings bag.
   */
  public function __construct(\Civi\Core\SettingsBag $settingsBag, array $settingsMeta) {
    $this->settingsBag = $settingsBag;
    $this->settingsMeta = $settingsMeta;
  }

  /**
   * @param array $params
   * @param string $type
   * @param string $expr
   *
   * @return mixed
   */
  protected function applyOption(array &$params, string $type, string $expr): void {
    $aliases = [
      // 'a' => 'append',
      // 'm' => 'update',
      'm' => 'merge',
      'd' => 'delete',
    ];
    $type = $aliases[$type] ?? $type;

    switch ($type) {
      // // +a some_rows={"new":"row"}
      // case 'append':
      //   [$key, $value] = $this->parseAssignment($expr);
      //   $keyParts = explode($this->pathDelimiter, $key);
      //   $this->includeBaseSetting($params, $keyParts[0]);
      //
      //   $fullValue = \CRM_Utils_Array::pathGet($params, $keyParts, []);
      //   if (!in_array($value, $fullValue)) {
      //     $fullValue[] = $value;
      //   }
      //   ArrayUtil::pathSet($params, $keyParts, $fullValue);
      //   break;

      // // +u mailing_backend.outBoundOption=2
      // case 'update':
      //   [$key, $value] = $this->parseAssignment($expr);
      //   $keyParts = explode($this->pathDelimiter, $key);
      //   $this->includeBaseSetting($params, $keyParts[0]);
      //
      //   ArrayUtil::pathSet($params, $keyParts, $value);
      //   break;

      // +m mailing_backend.outBoundOption=2
      // +m some_list[]=100
      // +m some_rows[]={"field1":"value1","field2":"value2"}
      case 'merge':
        [$key, $op, $value] = $this->parseValueUpdate($expr);
        $keyParts = explode($this->pathDelimiter, $key);
        $this->includeBaseSetting($params, $keyParts[0]);

        if ($op === '=') {
          \CRM_Utils_Array::pathSet($params, $keyParts, $value);
        }
        elseif ($op === '[]=') {
          $fullValue = \CRM_Utils_Array::pathGet($params, $keyParts, []);
          $fullValue[] = $value;
          \CRM_Utils_Array::pathSet($params, $keyParts, $fullValue);
        }
        else {
          throw new \RuntimeException("Unrecognized value operator: $op");
        }
        break;

      // +d mailing_backend
      // +d mailing_backend.outBound_option
      case 'delete':
        $keyParts = explode($this->pathDelimiter, $expr);
        if (count($keyParts) === 1) {
          $params[$expr] = NULL;
        }
        else {
          \CRM_Utils_Array::pathUnset($params, $keyParts);
        }
        break;

      default:
        throw new \RuntimeException("Unrecognized option: +$type");
    }
  }

  protected function includeBaseSetting(array &$params, string $settingKey) {
    [$encode, $decode] = SettingCodec::codec($this->settingsMeta, $settingKey);
    if (!isset($params[$settingKey])) {
      $params[$settingKey] = $decode($this->settingsBag->get($settingKey));
    }
  }

  /**
   * @param string $expr
   *   Ex: a=b
   *   Ex: a[]=b
   * @return array
   *   Ex: ['a', '=', 'b']
   *   Ex: ['a', '[]=', 'b']
   */
  public function parseValueUpdate($expr) {
    if (preg_match('/^([a-zA-Z0-9_:\.]+)\s*(=|\[\]=)\s*(.*)$/', $expr, $matches)) {
      return [$matches[1], $matches[2], $this->parseValueExpr($matches[3])];
    }
    else {
      throw new \RuntimeException("Error parsing \"value\": $expr");
    }
  }

  protected function parseValueExpr($expr) {
    if (strpos('{["\'', $expr[0]) !== FALSE) {
      return $this->parseJsonNoisily($expr);
    }
    else {
      return $this->castNumberSoftly($expr);
    }
  }

  private function castNumberSoftly($value) {
    if (is_string($value) && preg_match(';^\d+$;', $value)) {
      return (int) $value;
    }
    elseif (is_string($value) && preg_match(';^\d+\.\d+$;', $value)) {
      return (float) $value;
    }
    return $value;
  }

}
