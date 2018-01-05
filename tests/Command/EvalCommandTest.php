<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group php
 */
class EvalCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testEv() {
    $helloPhp = escapeshellarg('printf("eval says version is %s\n", CRM_Utils_System::version());');
    $p = Process::runOk($this->cv("ev $helloPhp"));
    $this->assertRegExp('/^eval says version is [0-9a-z\.]+\s*$/', $p->getOutput());
  }

  public function testPhpEval() {
    $helloPhp = escapeshellarg('printf("eval says version is %s\n", CRM_Utils_System::version());');
    $p = Process::runOk($this->cv("ev $helloPhp"));
    $this->assertRegExp('/^eval says version is [0-9a-z\.]+\s*$/', $p->getOutput());
  }

  public function testBoot() {
    $checkBoot = escapeshellarg('echo (function_exists("drupal_add_js") || function_exists("wp_redirect") || class_exists("JFactory")) ? "found" : "not-found";');

    $p1 = Process::runOk($this->cv("ev $checkBoot"));
    $this->assertRegExp('/^found$/', $p1->getOutput());
  }

  public function testTestMode() {
    $checkUf = escapeshellarg('return CIVICRM_UF;');

    $p1 = Process::runOk($this->cv("ev $checkUf"));
    $this->assertRegExp('/(Drupal|Joomla|WordPress|Backdrop)/i', $p1->getOutput());

    $p1 = Process::runOk($this->cv("ev -t $checkUf"));
    $this->assertRegExp('/UnitTests/i', $p1->getOutput());
  }

}
