<?php
namespace Civi\Cv\Util;

/**
 * @group std
 * @group util
 */
class Api4ArgParserTest extends \PHPUnit_Framework_TestCase {

  public function getGoodExamples() {
    $exs = [];
    $exs[] = [
      ['limit=10', '+select=display_name'],
      ['limit' => 10, 'select' => ['display_name']],
    ];
    $exs[] = [
      ['limit=10', '+select=display_name', '+select=contact_type'],
      ['limit' => 10, 'select' => ['display_name', 'contact_type']],
    ];
    $exs[] = [
      ['limit=10', 'select:display_name contact_type', '+where:id > 123'],
      [
        'limit' => 10,
        'select' => ['display_name', 'contact_type'],
        'where' => [['id', '>', '123']]
      ],
    ];
    $exs[] = [
      ['+where:display_name like "alice%"'],
      [
        'where' => [
          ['display_name', 'like', 'alice%'],
        ],
      ],
    ];

    $exs[] = [
      ['limit=10', 'select=["display_name"]'],
      ['limit' => 10, 'select' => ['display_name']],
    ];
    $exs[] = [
      ['limit=10', '+where=["first_name","not like","foo%"]'],
      ['limit' => 10, 'where' => [['first_name', 'not like', 'foo%']]],
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
    $this->assertEquals($expected, $actual);
  }

  public function getBadExamples() {
    $exs = [];
    $exs[] = [['{foo']];
    $exs[] = [['foo={bar']];
    $exs[] = [['foo={"bar":foo"']];
    $exs[] = [['+foo=[bar']];
    $exs[] = [['foo="bar']];
    return $exs;
  }

  /**
   * @param $input
   * @dataProvider getBadExamples
   * @expectedException \RuntimeException
   */
  public function testBadInput($input) {
    $p = new Api4ArgParser();
    $p->parse($input);
  }

  public function testExplode() {
    $p = new Api4ArgParser();
    $this->assertEquals(['ab', '>=', 'cd'], $p->explode('ab >= cd'));
    $this->assertEquals(['ab', '>=', '"cd ef"'], $p->explode('ab >= "cd ef"'));
  }

}
