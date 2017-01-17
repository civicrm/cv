<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

class ApiCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
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
    $p = Process::runOk(new \Symfony\Component\Process\Process("echo $input | {$this->cv} api Contact.get --in=json"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(!empty($data['values']));
    $this->assertEquals(1, count($data['values']));
    foreach ($data['values'] as $row) {
      $this->assertTrue(!empty($row['display_name']));
    }
  }

}
