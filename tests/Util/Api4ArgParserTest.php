<?php
namespace Civi\Cv\Util;

/**
 * @group std
 * @group util
 */
class Api4ArgParserTest extends \PHPUnit\Framework\TestCase {

  public function getGoodExamples() {
    $exs = [];
    $exs[] = [
      ['+v=first_name=foo', '+v=middle_name=Foo Bar', '+v=last_name="Whiz Bang"'],
      [
        'values' => [
          'first_name' => 'foo',
          'middle_name' => 'Foo Bar',
          'last_name' => 'Whiz Bang',
        ],
      ],
    ];
    $exs[] = [
      ['limit=10', '+select=display_name'],
      ['limit' => 10, 'select' => ['display_name']],
    ];
    $exs[] = [
      ['+limit=100'],
      ['limit' => 100],
    ];
    $exs[] = [
      ['+limit=15@90'],
      ['limit' => 15, 'offset' => 90],
    ];
    $exs[] = [
      ['limit=10', '+select=display_name', '+select=contact_type'],
      ['limit' => 10, 'select' => ['display_name', 'contact_type']],
    ];
    $exs[] = [
      ['limit=10', '+select=display_name,contact_type'],
      ['limit' => 10, 'select' => ['display_name', 'contact_type']],
    ];
    $exs[] = [
      ['limit=10', '+select', 'display_name,contact_type'],
      ['limit' => 10, 'select' => ['display_name', 'contact_type']],
    ];
    $exs[] = [
      ['limit=10', '+select=display_name,contact_type', '+where=id > 123'],
      [
        'limit' => 10,
        'select' => ['display_name', 'contact_type'],
        'where' => [['id', '>', '123']],
      ],
    ];
    $exs[] = [
      ['+w=id is not null', '+w=id>=234'],
      ['where' => [['id', 'IS NOT NULL'], ['id', '>=', '234']]],
    ];
    $exs[] = [
      ['+w=id is empty', '+w=id>=234'],
      ['where' => [['id', 'IS EMPTY'], ['id', '>=', '234']]],
    ];
    $exs[] = [
      ['+w=id Is Not Empty', '+w=id>=234'],
      ['where' => [['id', 'IS NOT EMPTY'], ['id', '>=', '234']]],
    ];

    $exs[] = [
      ['+w', 'foo:bar=apple', '+where', 'whiz.bang=banana'],
      ['where' => [['foo:bar', '=', 'apple'], ['whiz.bang', '=', 'banana']]],
    ];
    $exs[] = [
      ['+v', 'foo:bar=apple', '+value', 'whiz.bang=banana'],
      ['values' => ['foo:bar' => 'apple', 'whiz.bang' => 'banana']],
    ];
    $exs[] = [
      ['+where:display_name like "alice%"', '+w:id >= 234'],
      [
        'where' => [
          ['display_name', 'LIKE', 'alice%'],
          ['id', '>=', 234],
        ],
      ],
    ];

    $exs[] = [
      ['+orderBy:last_name asc'],
      ['orderBy' => ['last_name' => 'ASC']],
    ];
    $exs[] = [
      ['+orderBy:last_name, first_name DESC'],
      ['orderBy' => ['last_name' => 'ASC', 'first_name' => 'DESC']],
    ];
    $exs[] = [
      ['limit=10', 'select=["display_name"]'],
      ['limit' => 10, 'select' => ['display_name']],
    ];
    $exs[] = [
      ['colors={"red":"#f00","green":"#0f0"}'],
      ['colors' => ['red' => '#f00', 'green' => '#0f0']],
    ];
    $exs[] = [
      ['{"version":4,"select":["foo"]}'],
      ['version' => 4, 'select' => ['foo']],
    ];
    $exs[] = [
      ['version=4', 'limit=10', '{"version":44,"select":["foo"]}'],
      ['version' => 44, 'limit' => 10, 'select' => ['foo']],
    ];
    $exs[] = [
      ['+v:blank=', '+v:zero=0'],
      ['values' => ['blank' => '', 'zero' => '0']],
    ];
    return $exs;
  }

  /**
   * @param $input
   * @param $expected
   * @dataProvider getGoodExamples
   */
  public function testGoodInput($input, $expected) {
    $p = new Api4ArgParser();
    $actual = $p->parse($input);
    // print_r(['expected' => $expected, 'actual' => $actual]);
    $this->assertEquals($expected, $actual);
  }

  public function getBadExamples() {
    $exs = [];
    $exs[] = [['{foo']];
    $exs[] = [['foo={bar']];
    $exs[] = [['foo={"bar":foo"']];
    $exs[] = [['+foo=[bar']];
    $exs[] = [['foo="bar']];
    $exs[] = [['foo is bar']];
    return $exs;
  }

  /**
   * @param $input
   * @dataProvider getBadExamples
   */
  public function testBadInput($input) {
    try {
      $p = new Api4ArgParser();
      $p->parse($input);
      $this->fail('Expected a runtime exception');
    }
    catch (\RuntimeException $e) {
      $this->assertMatchesRegularExpression(';(Unrecognized option|Failed to parse);', $e->getMessage());
    }
  }

  public function testParseWhere() {
    $p = new Api4ArgParser();
    $this->assertEquals(['ab', '>=', '123'], $p->parseWhere('ab>=123'));
    $this->assertEquals(['ab', '<=', '123'], $p->parseWhere('ab<= 123'));
    $this->assertEquals(['ab', 'NOT IN', [1, 2, 3]], $p->parseWhere('ab not in [1,2,3]'));
    $this->assertEquals(['ab', '>=', 'cd'], $p->parseWhere('ab >= cd'));
    $this->assertEquals(['ab', '>=', 'cd ef'], $p->parseWhere('ab >= cd ef'));
    $this->assertEquals(['ab', '>=', 'cd ef'], $p->parseWhere('ab >= "cd ef"'));
    $this->assertEquals(['ab', 'NOT LIKE', 'abcd%'], $p->parseWhere('ab not like abcd%'));
    $this->assertEquals(['ab', 'NOT LIKE', 'abcd%'], $p->parseWhere('ab not like "abcd%"'));

  }

}
