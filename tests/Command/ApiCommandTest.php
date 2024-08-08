<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class ApiCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testApi() {
    $p = Process::runOk($this->cv("api System.get"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(!empty($data['values']));
    foreach ($data['values'] as $row) {
      $this->assertTrue(!empty($row['version']));
      $this->assertTrue(!empty($row['uf']));
    }
  }

  public function testCsv() {
    $p = Process::runOk($this->cv("api OptionValue.get option_group_id=activity_type return=option_group_id,name rowCount=2 --out=csv"));
    $lines = explode("\n", trim($p->getOutput()));
    $expected = array(
      'option_group_id,name',
      '2,Meeting',
      '2,"Phone Call"',
    );
    $this->assertEquals($expected, $lines);
  }

  public function testCsvMisuse() {
    $p = Process::runOk($this->cv("api OptionValue.getsingle rowCount=1 --out=csv"));
    $this->assertMatchesRegularExpression('/The output format "csv" only works with tabular data. Try using a "get" API. Forcing format to "json-pretty"./', $p->getErrorOutput());
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(!empty($data['option_group_id']));
  }

  public function testQuiet() {
    $p = Process::runOk($this->cv("api -q System.get"));
    $this->assertEmpty($p->getOutput());
    $this->assertEmpty($p->getErrorOutput());
  }

  public function testQuietError() {
    $p = Process::runFail($this->cv("api -q System.getzz"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(!empty($data['is_error']));
    $this->assertTrue(!empty($data['error_message']));
  }

  public function testApiPipe() {
    $input = escapeshellarg(json_encode(array(
      'options' => array('limit' => 1),
    )));
    $p = Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline("echo $input | {$this->cv} api Contact.get --in=json"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(!empty($data['values']));
    $this->assertEquals(1, count($data['values']));
    foreach ($data['values'] as $row) {
      $this->assertTrue(!empty($row['display_name']));
    }
  }

}
