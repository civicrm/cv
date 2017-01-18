<?php
namespace Civi\Cv\Command;

use Civi\Cv\Exception\ProcessErrorException;
use Civi\Cv\Util\Process;

class ExtensionListCommandTest extends \Civi\Cv\CivilTestCase {

  /**
   * List extensions using local or remote filters.
   */
  public function testGetLocalOrRemote() {
    $regex = '/^\| /';

    $localProc = Process::runOk($this->cv('ext:list -L'));
    $localLines = preg_grep($regex, explode("\n", $localProc->getOutput() . $localProc->getErrorOutput()));
    $this->assertTrue(count($localLines) > 2);
    $this->assertNotEmpty(preg_grep('/^\| local/', $localLines));
    $this->assertEmpty(preg_grep('/^\| remote/', $localLines));

    $remoteProc = Process::runOk($this->cv('ext:list -R'));
    $remoteLines = preg_grep($regex, explode("\n", $remoteProc->getOutput() . $remoteProc->getErrorOutput()));
    $this->assertTrue(count($remoteLines) > 2);
    $this->assertEmpty(preg_grep('/^\| local/', $remoteLines));
    $this->assertNotEmpty(preg_grep('/^\| remote/', $remoteLines));

    $allProc = Process::runOk($this->cv('ext:list -LR'));
    $allLines = preg_grep($regex, explode("\n", $allProc->getOutput() . $allProc->getErrorOutput()));
    $this->assertTrue(count($allLines) > 2);
    $this->assertNotEmpty(preg_grep('/^\| local/', $allLines));
    $this->assertNotEmpty(preg_grep('/^\| remote/', $allLines));

    $defaultProc = Process::runOk($this->cv('ext:list'));
    $defaultLines = preg_grep($regex, explode("\n", $defaultProc->getOutput() . $defaultProc->getErrorOutput()));
    $this->assertTrue(count($defaultLines) > 3);
    $this->assertEquals($defaultLines, $allLines, 'The default behavior should match -LR');

    $this->assertEquals($this->digest($localLines, $remoteLines), $this->digest($allLines), 'The full list should be the union of the local and remote lists');
  }

  /**
   * List extensions using a regular expression.
   */
  public function testGetRegex() {
    $p = Process::runOk($this->cv('ext:list'));
    $this->assertRegexp('/remote.*cividiscount.*org.civicrm.module.cividiscount/', $p->getOutput());

    $p = Process::runOk($this->cv('ext:list /org.civicrm/')); // matches key
    $this->assertRegexp('/remote.*cividiscount.*org.civicrm.module.cividiscount/', $p->getOutput());

    $p = Process::runOk($this->cv('ext:list /^cividiscount/')); // matches name
    $this->assertRegexp('/remote.*cividiscount.*org.civicrm.module.cividiscount/', $p->getOutput());

    $p = Process::runOk($this->cv('ext:list /^com\./')); // matches name
    $this->assertNotRegexp('/remote.*cividiscount.*org.civicrm.module.cividiscount/', $p->getOutput());
  }

  /**
   * Combine a bunch of arrays into a normalized form, sorted and only containing
   * unique rows.
   *
   * e.g. $combined = $this->digest($array_a, $array_b);
   *
   * @return array
   */
  public function digest() {
    $args = func_get_args();
    $rows = array();
    foreach ($args as $arg) {
      foreach ($arg as $line) {
        if (!empty($line)) {
          // Tabular output includes a variable number of spaces. Normalize.
          $rows[] = preg_replace('/ +/', ' ', $line);
        }
      }
    }

    sort($rows);
    return array_values(array_unique($rows));
  }

}
