<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class SettingLifecycleTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testScalar() {
    $vset = $this->cvOk('vset dummy_scalar_1=100 dummy_scalar_2="More text"');
    $this->assertTableHasRow(['domain', 'dummy_scalar_1', 100, 'explicit'], $vset);
    $this->assertTableHasRow(['domain', 'dummy_scalar_2', '"More text"', 'explicit'], $vset);

    $vget = $this->cvOk('vget dummy_scalar_1 dummy_scalar_2');
    $this->assertTableHasRow(['domain', 'dummy_scalar_1', 100, 'explicit'], $vget);
    $this->assertTableHasRow(['domain', 'dummy_scalar_2', '"More text"', 'explicit'], $vget);

    $vgetRegex = $this->cvOk('vget /^dummy/');
    $this->assertTableHasRow(['domain', 'dummy_scalar_1', 100, 'explicit'], $vgetRegex);
    $this->assertTableHasRow(['domain', 'dummy_scalar_2', '"More text"', 'explicit'], $vgetRegex);

    $this->cvOk('vdel dummy_scalar_1 dummy_scalar_2');
    $this->assertNotRegExp('/dummy_scalar/', $this->cvOk('vget /dummy/'));
  }

  public function testArray() {
    $vset = $this->cvOk('vset dummy_array=\'[1, 2, 3]\'');
    $this->assertTableHasRow(['domain', 'dummy_array', '\[1,2,3\]', 'explicit'], $vset);

    $vget = $this->cvOk('vget dummy_array');
    $this->assertTableHasRow(['domain', 'dummy_array', '\[1,2,3\]', 'explicit'], $vget);

    $vgetRegex = $this->cvOk('vget /^dummy/');
    $this->assertTableHasRow(['domain', 'dummy_array', '\[1,2,3\]', 'explicit'], $vgetRegex);

    $vsetMerge = $this->cvOk('vset +m dummy_array[]=4 +m "dummy_array[]=foo bar"');
    $this->assertTableHasRow(['domain', 'dummy_array', '\[1,2,3,4,"foo bar"\]', 'explicit'], $vsetMerge);

    $vgetFinal = $this->cvOk('vget dummy_array');
    $this->assertTableHasRow(['domain', 'dummy_array', '\[1,2,3,4,"foo bar"\]', 'explicit'], $vgetFinal);

    Process::runOk($this->cv('vdel dummy_array'));

    $this->assertNotRegExp('/dummy_array/', $this->cvOk('vget /dummy/'));
  }

  public function testObject() {
    $vset = $this->cvOk('vset dummy_obj=\'{"a": 10}\'');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10\}', 'explicit'], $vset);

    $vget = $this->cvOk('vget dummy_obj');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10\}', 'explicit'], $vget);

    $vgetRegex = $this->cvOk('vget /^dummy/');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10\}', 'explicit'], $vgetRegex);

    $vsetMerge = $this->cvOk('vset +m dummy_obj.b=20 +m dummy_obj.de.ep=30');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10,"b":20,"de":\{"ep":30\}\}', 'explicit'], $vsetMerge);

    $vgetFinal = $this->cvOk('vget /^dummy/');
    $this->assertTableHasRow(['domain', 'dummy_obj', '\{"a":10,"b":20,"de":\{"ep":30\}\}', 'explicit'], $vgetFinal);

    Process::runOk($this->cv('vdel dummy_obj'));
    $this->assertNotRegExp('/dummy_obj/', $this->cvOk('vget /dummy/'));
  }

  protected function assertTableHasRow($expectRow, $actualTable) {
    $vr = '\s*\|\s*';
    $regex = '/' . $vr . implode($vr, $expectRow) . $vr . '/';
    $this->assertRegExp($regex, $actualTable);
  }

}
