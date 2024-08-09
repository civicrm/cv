<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class ApiBatchCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testApi_NumArray() {
    $input = escapeshellarg(
      json_encode(array(
        // Don't care about ID# or existence -- just want a quick dummy call.
        array('Contact', 'get', array('id' => 100)),
        array('Contact', 'get', array('id' => 101)),
      ))
    );
    $p = Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline("echo $input | {$this->cv} api:batch"));
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data[0]['is_error']));
    $this->assertTrue(isset($data[1]['is_error']));
    $this->assertTrue(isset($data[0]['error_message']) || isset($data[0]['values']));
    $this->assertTrue(isset($data[1]['error_message']) || isset($data[1]['values']));
  }

  public function testApiBatch_MultiLine() {
    // For example API calls, don't care about ID# or existence -- just want a quick dummy call.
    $jsonNumArray = json_encode(array(
      array('Contact', 'get', array('id' => 100, 'options.ignore-me' => "foo\nbar")),
      array('Contact', 'get', array('id' => 101)),
    ));
    $jsonAssocArray = json_encode(array(
      'foo' => array('Contact', 'get', array('id' => 100)),
      'bar' => array('Contact', 'get', array('id' => 101)),
    ));
    $input = ($jsonNumArray . "\n" . $jsonAssocArray);

    $p = \Symfony\Component\Process\Process::fromShellCommandline("{$this->cv} api:batch");
    $p->setInput($input);
    $p = Process::runOk($p);

    $this->assertEmpty($p->getErrorOutput());
    $responses = explode("\n", $p->getOutput());

    $dataNumArray = json_decode($responses[0], 1);
    $this->assertTrue(isset($dataNumArray[0]['is_error']));
    $this->assertTrue(isset($dataNumArray[1]['is_error']));
    $this->assertTrue(isset($dataNumArray[0]['error_message']) || isset($dataNumArray[0]['values']));
    $this->assertTrue(isset($dataNumArray[1]['error_message']) || isset($dataNumArray[1]['values']));

    $dataAssocArray = json_decode($responses[1], 1);
    $this->assertTrue(isset($dataAssocArray['foo']['is_error']));
    $this->assertTrue(isset($dataAssocArray['bar']['is_error']));
    $this->assertTrue(isset($dataAssocArray['foo']['error_message']) || isset($dataAssocArray['foo']['values']));
    $this->assertTrue(isset($dataAssocArray['bar']['error_message']) || isset($dataAssocArray['bar']['values']));
  }

}
