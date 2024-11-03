<?php
namespace Civi\Cv\Util;

class Api4ArgEncoder {

  public function encode(string $entity, string $action, array $params = []): string {
    $parts = $this->encodeParams($params);
    $suffix = empty($parts) ? '' : ' ' . implode(" ", array_map([$this, 'escapeShellArg'], $parts));
    return 'cv api4 ' . $this->escapeShellArg("$entity.$action") . $suffix;
  }

  /**
   * @internal
   * @param array $params
   *   Ex: ['select' => ['foo', 'bar']]
   * @return array
   *   List of distinct CLI arguments
   *   Ex: ['+s', 'foo,bar']
   */
  public function encodeParams(array $params = []): array {
    $parts = [];
    $oddballs = [];
    $limitSet = FALSE;
    foreach ($params as $key => $value) {

      switch (TRUE) {
        case ($key === 'select'):
          array_push($parts, '+s', implode(',', $value));
          break;

        case ($key === 'where' && !array_intersect(array_column($value, 0), ['AND', 'OR', 'NOT'])):
          $symbolOperators = ['=', '<=' , '<', '>=', '>'];
          foreach ($value as $clause) {
            if (count($clause) < 3) {
              $expr = $this->render($clause[0], 'sj') . ' ' . $clause[1];
            }
            else {
              $pad = (in_array($clause[1], $symbolOperators) && mb_strlen($clause[2]) < 10) ? '' : ' ';
              $expr = $this->render($clause[0], 'sj') . $pad . $clause[1] . $pad . $this->render($clause[2], 'bnwj');
            }
            array_push($parts, '+w', $expr);
          }
          break;

        case ($key === 'orderBy'):
          foreach ($value as $field => $dir) {
            array_push($parts, '+o', ($dir === 'ASC') ? "$field" : "$field $dir");
          }
          break;

        case ($key === 'values'):
          foreach ($value as $field => $val) {
            array_push($parts, '+v', $field . '=' . $this->render($val, 'bnsj'));
          }
          break;

        case ($key === 'limit' || $key === 'offset'):
          if (!$limitSet) {
            $limitSet = TRUE;
            $suffix = isset($params['offset']) ? '@' . $params['offset'] : '';
            array_push($parts, '+l', ($params['limit'] ?? '0') . $suffix);
          }
          break;

        default:
          // If we can get away with "key=value", use that
          if (preg_match('/^[a-zA-Z0-9_:]+$/', $key)) {
            array_push($parts, $key . '=' . $this->render($value, 'bnsj'));
          }
          // Otherwise, fallback to straight JSON
          else {
            $oddballs[$key] = $value;
          }
      }
    }
    if (!empty($oddballs)) {
      $parts[] = json_encode($oddballs);
    }
    return $parts;
  }

  /**
   * @param mixed $value
   * @param string $rules
   *   In most contexts, you can use JSON. But some also allow bare numbers, bare words, or some
   *   standard strings. Give a list of rendering rules that are valid in this context.
   *
   *   'b': Boolean (TRUE<=>1; FALSE<=>0)
   *   'w': Word (Bare word -- alphanumerics without spaces)
   *   'n': Number (Bare number -- numerical digits with optional decimal)
   *   's': Standard string (Alphanumerics and some limited punctuation that doesn't interfere with JSON, etc)
   *   'j': JSON
   * @return scalar
   */
  private function render($value, string $rules) {
    foreach (str_split($rules) as $rule) {
      if ($rule === 'b' && ($value === TRUE || $value === FALSE)) {
        return (int) $value;
      }
      elseif ($rule === 'n' && is_numeric($value)) {
        return $value;
      }
      elseif ($rule === 'w' && is_string($value) && preg_match('/^[a-zA-Z0-9_.]*$/', $value)) {
        return $value;
      }
      elseif ($rule === 's' && is_string($value) && preg_match('/^[a-zA-Z0-9_. :\/+-]*$/', $value)) {
        // NOTE: Strings that start with `"` should be encoded through JSON.
        return $value;
      }
      elseif ($rule === 'j') {
        return json_encode($value);
      }
    }
  }

  private function escapeShellArg(string $str): string {
    $shellMetaRegex = '/[' . preg_quote(' {}\'\"\\<>[]&;', '/') . ']/';
    if (!preg_match($shellMetaRegex, $str)) {
      return $str;
    }
    return escapeshellarg($str);
  }

}
