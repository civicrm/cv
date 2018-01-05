<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class DebugDispatcherCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testNoArg() {
    $p = Process::runOk($this->cv("debug:event-dispatcher"));
    $this->assertRegExp('/hook_civicrm_caseChange/', $p->getOutput());
    $this->assertRegExp('/hook_civicrm_post/', $p->getOutput());
    $this->assertRegExp('/civi.token.eval/', $p->getOutput());
  }

  public function testName() {
    $p = Process::runOk($this->cv("debug:event-dispatcher hook_civicrm_caseChange"));
    $this->assertRegExp('/hook_civicrm_caseChange/', $p->getOutput());
    $this->assertNotRegExp('/hook_civicrm_post/', $p->getOutput());
    $this->assertNotRegExp('/civi.token.eval/', $p->getOutput());
  }

  public function testRegExp() {
    $p = Process::runOk($this->cv("debug:event-dispatcher /^hook/"));
    $this->assertRegExp('/hook_civicrm_caseChange/', $p->getOutput());
    $this->assertRegExp('/hook_civicrm_post/', $p->getOutput());
    $this->assertNotRegExp('/civi.token.eval/', $p->getOutput());
  }

}
