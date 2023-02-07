<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group php
 */
class ScriptCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
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

  public function testScrNoArg() {
    $helloPhp = escapeshellarg(__DIR__ . '/hello-args.php');
    $p = Process::runOk($this->cv("scr $helloPhp"));
    $this->assertEquals("No arguments passed.\n", $p->getOutput());
  }

  public function testScrArgs() {
    $helloPhp = escapeshellarg(__DIR__ . '/hello-args.php');
    $p = Process::runOk($this->cv("scr $helloPhp one 'two and' three"));
    $this->assertEquals("0: one\n1: two and\n2: three\n", $p->getOutput());
  }

}
