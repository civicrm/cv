<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group ext
 */
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
   * Get all available columns
   */
  public function testGetAllColumns() {
    $p = Process::runOk($this->cv('ext:list -a'));
    $exts = json_decode($p->getOutput(), 1);
    $this->assertTrue(count($exts) > 1);
    foreach ($exts as $ext) {
      $this->assertMatchesRegularExpression('/^[_\w]+$/', $ext['name'], 'name should be well-formed');
      $this->assertMatchesRegularExpression('/^[\.\-_\w]+$/', $ext['key'], 'key should be well-formed');
      $this->assertMatchesRegularExpression('/^\d[\.\-_\w]*$/', $ext['version'], 'version should be well-formed');
      if (!empty($ext['downloadUrl'])) {
        $this->assertMatchesRegularExpression(';^https?://;', $ext['downloadUrl'], 'downloadUrl should be well-formed');
      }
      $this->assertMatchesRegularExpression(';^(|unknown|manual|current|available)$;', $ext['upgrade'], 'upgrade should be well-formed');
      if (!empty($ext['upgradeVersion'])) {
        $this->assertMatchesRegularExpression('/^\d[\.\-_\w]*$/', $ext['upgradeVersion'], 'upgradeVersion should be well-formed');
      }
      if ($ext['location'] === 'local') {
        $this->assertMatchesRegularExpression(';^(installed|uninstalled|disabled|unknown|installed-missing|disabled-missing)$;', $ext['status'], 'status should be well-formed for local extensions');
      }
      else {
        $this->assertEquals('', $ext['status'], 'status should be empty for remote extensions');
      }
      $this->assertTrue(isset($ext['nameKey']), 'nameKey should be set');
      $this->assertTrue(isset($ext['label']), 'label should be set');
      if (!empty($ext['path'])) {
        $this->assertTrue(file_exists($ext['path']), 'path should be valid');
      }
      if (!empty($ext['relPath'])) {
        $this->assertMatchesRegularExpression(';^\[(civicrm\.|cms\.);', $ext['relPath'], 'relPath should start with a path variable');
      }
    }
  }

  /**
   * List extensions using a regular expression.
   */
  public function testGetRegex() {
    $p = Process::runOk($this->cv('ext:list'));
    $hasCiviDiscount = '/remote.*cividiscount \(org.civicrm.module.cividiscount\)/';
    $this->assertMatchesRegularExpression($hasCiviDiscount, $p->getOutput());

    // matches key
    $p = Process::runOk($this->cv('ext:list /org.civicrm/'));
    $this->assertMatchesRegularExpression($hasCiviDiscount, $p->getOutput());

    // matches name
    $p = Process::runOk($this->cv('ext:list /^cividiscount/'));
    $this->assertMatchesRegularExpression($hasCiviDiscount, $p->getOutput());

    // matches name
    $p = Process::runOk($this->cv('ext:list /^com\./'));
    $this->assertDoesNotMatchRegularExpression($hasCiviDiscount, $p->getOutput());
  }

  /**
   * Get the extension data in an alternate format, eg JSON.
   */
  public function testGetJson() {
    $p = Process::runOk($this->cv('ext:list /^cividiscount$/ --out=json --remote'));
    $data = json_decode($p->getOutput(), 1);
    $this->assertEquals(1, count($data));
    $this->assertEquals('cividiscount', $data[0]['name']);
    $this->assertEquals('org.civicrm.module.cividiscount', $data[0]['key']);

    $p = Process::runOk($this->cv('ext:list /^cividiscount$/ --out=json --remote --columns=name,version'));
    $data = json_decode($p->getOutput(), 1);
    $this->assertEquals(1, count($data));
    $this->assertEquals('cividiscount', $data[0]['name']);
    $this->assertFalse(isset($data[0]['key']));
    $this->assertNotEmpty($data[0]['version']);
  }

  /**
   * Get the extensions which have a given status
   */
  public function testFilterByStatus() {
    $p = Process::runOk($this->cv('ext:list -Li --out=json'));
    $data = json_decode($p->getOutput(), 1);
    foreach ($data as $row) {
      $this->assertEquals('installed', $row['status']);
    }

    $p = Process::runOk($this->cv('ext:list -L --statuses=uninstalled --out=json'));
    $data = json_decode($p->getOutput(), 1);
    foreach ($data as $row) {
      $this->assertTrue(in_array($row['status'], array('uninstalled')));
    }

    $p = Process::runOk($this->cv('ext:list -L --statuses=disabled,uninstalled --out=json'));
    $data = json_decode($p->getOutput(), 1);
    foreach ($data as $row) {
      $this->assertTrue(in_array($row['status'], array('disabled', 'uninstalled')));
    }
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
