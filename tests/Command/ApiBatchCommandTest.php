<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

class ApiBatchCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testApi_NumArray() {
    $input = escapeshellarg(json_encode(array(
      // Don't care about ID# or existence -- just want a quick dummy call.
      array('Contact', 'get', array('id' => 100)),
      array('Contact', 'get', array('id' => 101)),
    )));
    $p = Process::runOk(new \Symfony\Component\Process\Process("echo $input | {$this->cv} api:batch"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data[0]['is_error']));
    $this->assertTrue(isset($data[1]['is_error']));
    $this->assertTrue(isset($data[0]['error_message']) || isset($data[0]['values']));
    $this->assertTrue(isset($data[1]['error_message']) || isset($data[1]['values']));
  }

  public function testApi_AssocArray() {
    $input = escapeshellarg(json_encode(array(
      // Don't care about ID# or existence -- just want a quick dummy call.
      'foo' => array('Contact', 'get', array('id' => 100)),
      'bar' => array('Contact', 'get', array('id' => 101)),
    )));
    $p = Process::runOk(new \Symfony\Component\Process\Process("echo $input | {$this->cv} api:batch"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data['foo']['is_error']));
    $this->assertTrue(isset($data['foo']['error_message']) || isset($data['foo']['values']));
    $this->assertTrue(isset($data['bar']['error_message']) || isset($data['bar']['values']));
  }

}
