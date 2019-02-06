<?php
namespace Civi\Cv\Util;

/**
 * @group std
 * @group util
 */
class Api4ArgParserTest extends \PHPUnit_Framework_TestCase {

  public function getExamples() {
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
      ['limit:10', '+select:display_name', '+select:contact_type'],
      ['limit' => 10, 'select' => ['display_name', 'contact_type']],
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
    return $exs;
  }

  /**
   * @param $input
   * @param $expected
   * @dataProvider getExamples
   */
  public function testParser($input, $expected) {
    $p = new Api4ArgParser();
    $actual = $p->parse($input);
    $this->assertEquals($expected, $actual);
  }

}
