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
    $this->assertMatchesRegularExpression('/^version [0-9a-z\.]+$/', $p->getOutput());
  }

  public function testPhpScript() {
    $helloPhp = escapeshellarg(__DIR__ . '/hello-world.php');
    $p = Process::runOk($this->cv("php:script $helloPhp"));
    $this->assertMatchesRegularExpression('/^version [0-9a-z\.]+$/', $p->getOutput());
  }

  public function testScrNoArg() {
    $helloPhpFile = __DIR__ . '/hello-args.php';
    $helloPhpEsc = escapeshellarg(__DIR__ . '/hello-args.php');
    $p = Process::runOk($this->cv("scr $helloPhpEsc"));
    $this->assertEquals("Count: 1\n0: $helloPhpFile\n", $p->getOutput());
  }

  public function testScrArgs() {
    $helloPhpFile = __DIR__ . '/hello-args.php';
    $helloPhpEsc = escapeshellarg(__DIR__ . '/hello-args.php');
    $p = Process::runOk($this->cv("scr $helloPhpEsc one 'two and' three"));
    $this->assertEquals("Count: 4\n0: $helloPhpFile\n1: one\n2: two and\n3: three\n", $p->getOutput());
  }

}
