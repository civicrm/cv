<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

class ApiCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testApi() {
    $p = Process::runOk($this->cv("api System.get --out=json"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(!empty($data['values']));
    foreach ($data['values'] as $row) {
      $this->assertTrue(!empty($row['version']));
      $this->assertTrue(!empty($row['uf']));
    }
  }

  public function testApiPipe() {
    $input = escapeshellarg(json_encode(array(
      'options' => array('limit' => 1),
    )));
    $p = Process::runOk(new \Symfony\Component\Process\Process("echo $input | {$this->cv} api Contact.get --json"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(!empty($data['values']));
    $this->assertEquals(1, count($data['values']));
    foreach ($data['values'] as $row) {
      $this->assertTrue(!empty($row['display_name']));
    }
  }

}
