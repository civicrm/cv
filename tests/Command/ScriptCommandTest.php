<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group php
 */
class ScriptCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testScr() {
    $helloPhp = escapeshellarg(__DIR__ . '/hello-world.php');
    $p = Process::runOk($this->cv("scr $helloPhp"));
    $this->assertRegExp('/^version [0-9a-z\.]+$/', $p->getOutput());
  }

  public function testPhpScript() {
    $helloPhp = escapeshellarg(__DIR__ . '/hello-world.php');
    $p = Process::runOk($this->cv("php:script $helloPhp"));
    $this->assertRegExp('/^version [0-9a-z\.]+$/', $p->getOutput());
  }

}
