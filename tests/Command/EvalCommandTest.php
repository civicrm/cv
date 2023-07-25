<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group php
 */
class EvalCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testEv() {
    $helloPhp = escapeshellarg('printf("eval says version is %s\n", CRM_Utils_System::version());');
    $p = Process::runOk($this->cv("ev $helloPhp"));
    $this->assertRegExp('/^eval says version is [0-9a-z\.]+\s*$/', $p->getOutput());
  }

  public function testPhpEval_ReturnObj_json() {
    $phpCode = escapeshellarg('return (object)["ab"=>"cd"];');
    $p = Process::runOk($this->cv("ev $phpCode --out=json"));
    $this->assertEquals(0, $p->getExitCode());
    $this->assertRegExp(';"ab":\w*"cd\";', $p->getOutput());
  }

  public function testPhpEval_ReturnObj_shell() {
    $phpCodes = [
      'return (object)["ab"=>"cd"];',
      'return new class implements JsonSerializable { public function jsonSerialize() { return ["ab" => "cd"]; } };',
    ];

    foreach ($phpCodes as $phpCode) {
      $escaped = escapeshellarg($phpCode);
      $p = Process::runOk($this->cv("ev $escaped --out=shell"));
      $this->assertEquals(0, $p->getExitCode());
      $this->assertRegExp(';ab=["\']cd;', $p->getOutput());
    }
  }

  public function testPhpEval() {
    $helloPhp = escapeshellarg('printf("eval says version is %s\n", CRM_Utils_System::version());');
    $p = Process::runOk($this->cv("ev $helloPhp"));
    $this->assertRegExp('/^eval says version is [0-9a-z\.]+\s*$/', $p->getOutput());
  }

  public function testPhpEval_Exit0() {
    $p = Process::runDebug($this->cv("ev 'exit(0);'"));
    $this->assertEquals(0, $p->getExitCode());
  }

  public function testPhpEval_Exit1() {
    $p = Process::runDebug($this->cv("ev 'exit(1);'"));
    $this->assertEquals(1, $p->getExitCode());
  }

  public function testPhpEval_ExitCodeError() {
    $p = Process::runDebug($this->cv("ev 'invalid();'"));
    $this->assertEquals(255, $p->getExitCode());
  }

  public function testPhpEval_CvVar_Full() {
    $helloPhp = escapeshellarg('printf("my admin is %s\n", $GLOBALS["_CV"]["ADMIN_USER"]);');
    $p = Process::runOk($this->cv("ev --level=full $helloPhp"));
    $this->assertRegExp('/^my admin is \w+\s*$/', $p->getOutput());
  }

  public function testPhpEval_CvVar_CmsFull() {
    $helloPhp = escapeshellarg('printf("my admin is %s\n", $GLOBALS["_CV"]["ADMIN_USER"]);');
    $p = Process::runOk($this->cv("ev --level=cms-full $helloPhp"));
    $this->assertRegExp('/^my admin is \w+\s*$/', $p->getOutput());
  }

  public function testBoot() {
    $checkBoot = escapeshellarg('echo (function_exists("drupal_add_js") || function_exists("wp_redirect") || class_exists("JFactory") || class_exists("Drupal")) ? "found" : "not-found";');

    $p1 = Process::runOk($this->cv("ev $checkBoot"));
    $this->assertRegExp('/^found$/', $p1->getOutput());
  }

  public function getLevels() {
    return [
      ['settings'],
      ['classloader'],
      ['full'],
      ['cms-only'],
      ['cms-full'],
    ];
  }

  /**
   * @param string $level
   * @dataProvider getLevels
   */
  public function testBootLevels($level) {
    $checkBoot = escapeshellarg('echo "Hello world";');

    $p1 = Process::runOk($this->cv("ev $checkBoot --level=$level"));
    $this->assertRegExp('/^Hello world/', $p1->getOutput());
  }

  public function testTestMode() {
    $checkUf = escapeshellarg('return CIVICRM_UF;');

    $p1 = Process::runOk($this->cv("ev $checkUf"));
    $this->assertRegExp('/(Drupal|Joomla|WordPress|Backdrop)/i', $p1->getOutput());

    $p1 = Process::runOk($this->cv("ev -t $checkUf"));
    $this->assertRegExp('/UnitTests/i', $p1->getOutput());
  }

  /**
   * Tests --cwd option.
   */
  public function testEvalWithCwdOption() {
    // Go somewhere else, where cv won't work.
    chdir(sys_get_temp_dir());
    $cwdOpt = "--cwd=" . escapeshellarg($this->getExampleDir());
    $helloPhp = escapeshellarg('printf("eval says version is %s\n", CRM_Utils_System::version());');
    $p = Process::runOk($this->cv("ev $cwdOpt $helloPhp"));
    $this->assertRegExp('/^eval says version is [0-9a-z\.]+\s*$/', $p->getOutput());
  }

}
