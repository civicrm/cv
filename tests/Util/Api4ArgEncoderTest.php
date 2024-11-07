<?php

namespace Civi\Cv\Util;

/**
 * @group std
 * @group util
 */
class Api4ArgEncoderTest extends \PHPUnit\Framework\TestCase {

  public function getGoodExamples() {
    $exs = [];
    $exs[] = [
      ['limit' => 10, 'select' => ['display_name']],
      ['+l', '10', '+s', 'display_name'],
    ];
    $exs[] = [
      ['limit' => 100],
      ['+l', '100'],
    ];
    $exs[] = [
      ['limit' => 15, 'offset' => 90],
      ['+l', '15@90'],
    ];
    $exs[] = [
      ['limit' => 10, 'select' => ['display_name', 'contact_type']],
      ['+l', '10', '+s', 'display_name,contact_type'],
    ];
    $exs[] = [
      [
        'limit' => 10,
        'select' => ['display_name', 'contact_type'],
        'where' => [['id', '>', '123']],
      ],
      ['+l', '10', '+s', 'display_name,contact_type', '+w', 'id>123'],
    ];
    $exs[] = [
      // Where conditions with various operators
      ['where' => [['apple', 'IS NOT NULL'], ['banana', '>=', '234'], ['cherry', 'IS EMPTY'], ['date', 'IS NOT EMPTY']]],
      ['+w', 'apple IS NOT NULL', '+w', 'banana>=234', '+w', 'cherry IS EMPTY', '+w', 'date IS NOT EMPTY'],
    ];
    $exs[] = [
      // Where-conditions with synthetic fields
      ['where' => [['foo:bar', '=', 'apple'], ['whiz.bang', '=', 'banana']]],
      ['+w', 'foo:bar=apple', '+w', 'whiz.bang=banana'],
    ];
    $exs[] = [
      // Value-assignments with synthetic fields
      ['values' => ['foo:bar' => 'apple', 'whiz.bang' => 'banana']],
      ['+v', 'foo:bar=apple', '+v', 'whiz.bang=banana'],
    ];
    $exs[] = [
      [
        'where' => [
          ['contact_type', 'NOT LIKE', 'Individual'],
          ['display_name', 'LIKE', 'alice%'],
          ['id', '>=', 234],
        ],
      ],
      ['+w', 'contact_type NOT LIKE Individual', '+w', 'display_name LIKE "alice%"', '+w', 'id>=234'],
    ];

    $exs[] = [
      ['orderBy' => ['last_name' => 'ASC']],
      ['+o', 'last_name'],
    ];
    $exs[] = [
      // Multi-key order-by
      ['orderBy' => ['last_name' => 'ASC', 'first_name' => 'DESC']],
      ['+o', 'last_name', '+o', 'first_name DESC'],
    ];
    $exs[] = [
      // Top-level param with object data
      ['colors' => ['red' => '#f00', 'green' => '#0f0'], 'prefs' => ['foo' => TRUE, 'bar' => FALSE, 'whiz' => 0]],
      ['colors={"red":"#f00","green":"#0f0"}', 'prefs={"foo":true,"bar":false,"whiz":0}'],
    ];
    $exs[] = [
      // Mix of top-level params and selects
      ['version' => 4, 'select' => ['foo']],
      ['version=4', '+s', 'foo'],
    ];
    $exs[] = [
      // Value-assignments with empty things
      ['values' => ['blank' => '', 'zero' => '0']],
      ['+v', 'blank=', '+v', 'zero=0'],
    ];
    $exs[] = [
      // Value-assignments with various strings
      ['values' => ['truth' => 'true', 'fallacy' => 'false', 'code' => '{foo}', 'literal_single_quotes' => "ab'cd", 'literal_double_quotes' => 'ab"cd', 'expr' => 'No true Scotsman']],
      ['+v', 'truth=true', '+v', 'fallacy=false', '+v', 'code="{foo}"', '+v', 'literal_single_quotes="ab\'cd"', '+v', 'literal_double_quotes="ab\\"cd"', '+v', 'expr=No true Scotsman'],
    ];
    $exs[] = [
      // Top-level params with booleans
      ['checkPermissions' => TRUE, 'ignorePermissions' => FALSE],
      ['checkPermissions=1', 'ignorePermissions=0'],
    ];
    $exs[] = [
      // Value-assignments with booleans and numbers
      ['values' => ['is_active' => TRUE, 'is_inactive' => FALSE, 'my_int' => 123, 'my_float' => 1.23]],
      ['+v', 'is_active=1', '+v', 'is_inactive=0', '+v', 'my_int=123', '+v', 'my_float=1.23'],
    ];
    $exs[] = [
      // Bespoke parameter with funny key-name
      ['{foo}' => 123],
      ['{"{foo}":123}'],
    ];
    return $exs;
  }

  /**
   * @param $input
   * @param $expected
   * @dataProvider getGoodExamples
   */
  public function testGoodInput($input, $expected) {
    $encoder = new Api4ArgEncoder();
    $actual = $encoder->encodeParams($input);
    // print_r(['expected' => $expected, 'actual' => $actual]);
    $this->assertEquals($expected, $actual);

    $parser = new Api4ArgParser();
    $roundtrip = $parser->parse($actual);
    $this->assertEquals($input, $roundtrip, 'Round-trip of encoding and parsing should yield original');
  }

}
