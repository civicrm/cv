<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class SettingLifecycleTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
    $this->cvOk('vdel /^dummy/');
  }

  public function testScalar() {
    $vset = $this->cvOk('vset dummy_scalar_1=100 dummy_scalar_2="More text" dummy_blank= dummy_zero=0');
    $this->assertTableHasRow(['domain', 'dummy_scalar_1', 100, 'explicit'], $vset);
    $this->assertTableHasRow(['domain', 'dummy_scalar_2', '"More text"', 'explicit'], $vset);
    $this->assertTableHasRow(['domain', 'dummy_blank', '""', 'explicit'], $vset);
    $this->assertTableHasRow(['domain', 'dummy_zero', 0, 'explicit'], $vset);

    $vget = $this->cvOk('vget dummy_scalar_1 dummy_scalar_2');
    $this->assertTableHasRow(['domain', 'dummy_scalar_1', 100, 'explicit'], $vget);
    $this->assertTableHasRow(['domain', 'dummy_scalar_2', '"More text"', 'explicit'], $vget);

    $vgetRegex = $this->cvOk('vget /^dummy/');
    $this->assertTableHasRow(['domain', 'dummy_scalar_1', 100, 'explicit'], $vgetRegex);
    $this->assertTableHasRow(['domain', 'dummy_scalar_2', '"More text"', 'explicit'], $vgetRegex);

    $this->cvOk('vdel dummy_scalar_1 dummy_scalar_2');
    $this->assertDoesNotMatchRegularExpression('/dummy_scalar/', $this->cvOk('vget /dummy/'));
  }

  public function testList() {
    $vset = $this->cvOk('vset dummy_list=\'[1, 2, 3]\'');
    $this->assertTableHasRow(['domain', 'dummy_list', '\[1,2,3\]', 'explicit'], $vset);
    $this->assertTableHasRow(['domain', 'dummy_list', '\[1,2,3\]', 'explicit'], $this->cvOk('vget dummy_list'));
    $this->assertTableHasRow(['domain', 'dummy_list', '\[1,2,3\]', 'explicit'], $this->cvOk('vget /^dummy/'));

    $vsetAppend = $this->cvOk('vset +l dummy_list+=4 +l "dummy_list[]=foo bar"');
    $this->assertTableHasRow(['domain', 'dummy_list', '\[1,2,3,4,"foo bar"\]', 'explicit'], $vsetAppend);
    $this->assertTableHasRow(['domain', 'dummy_list', '\[1,2,3,4,"foo bar"\]', 'explicit'], $this->cvOk('vget dummy_list'));

    $vsetDel = $this->cvOk('vset +l !dummy_list.0');
    $this->assertTableHasRow(['domain', 'dummy_list', '\[2,3,4,"foo bar"\]', 'explicit'], $vsetDel);
    $this->assertTableHasRow(['domain', 'dummy_list', '\[2,3,4,"foo bar"\]', 'explicit'], $this->cvOk('vget dummy_list'));

    $vsetDel2 = $this->cvOk('vset +l "dummy_list-=foo bar"');
    $this->assertTableHasRow(['domain', 'dummy_list', '\[2,3,4\]', 'explicit'], $vsetDel2);
    $this->assertTableHasRow(['domain', 'dummy_list', '\[2,3,4\]', 'explicit'], $this->cvOk('vget dummy_list'));

    Process::runOk($this->cv('vdel dummy_list'));

    $this->assertDoesNotMatchRegularExpression('/dummy_list/', $this->cvOk('vget /dummy/'));
  }

  public function testObject() {
    $vset = $this->cvOk('vset dummy_obj=\'{"a": 10}\'');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10\}', 'explicit'], $vset);
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10\}', 'explicit'], $this->cvOk('vget dummy_obj'));
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10\}', 'explicit'], $this->cvOk('vget /^dummy/'));

    $vsetMerge = $this->cvOk('vset +o dummy_obj.b=20 +o dummy_obj.deep.x=30');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10,"b":20,"deep":\{"x":30\}\}', 'explicit'], $vsetMerge);
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10,"b":20,"deep":\{"x":30\}\}', 'explicit'], $this->cvOk('vget /^dummy/'));

    $vsetDel = $this->cvOk('vset +o !dummy_obj.a');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"b":20,"deep":\{"x":30\}\}', 'explicit'], $vsetDel);
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"b":20,"deep":\{"x":30\}\}', 'explicit'], $this->cvOk('vget /^dummy/'));

    $vsetDel2 = $this->cvOk('vset +o !dummy_obj.deep.x');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"b":20,"deep":\[\]\}', 'explicit'], $vsetDel2);
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"b":20,"deep":\[\]\}', 'explicit'], $this->cvOk('vget /^dummy/'));
    // We leave an empty object. For PHP-JSON, there's an ambiguity between `[]` and `{}`.

    Process::runOk($this->cv('vdel dummy_obj'));
    $this->assertDoesNotMatchRegularExpression('/dummy_obj/', $this->cvOk('vget /dummy/'));
  }

  protected function assertTableHasRow($expectRow, $actualTable) {
    $vr = '\s*\|\s*';
    $regex = '/' . $vr . implode($vr, $expectRow) . $vr . '/';
    $this->assertMatchesRegularExpression($regex, $actualTable);
  }

}
