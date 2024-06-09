<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class DebugDispatcherCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testNoArg() {
    $p = Process::runOk($this->cv("debug:event-dispatcher"));
    $this->assertMatchesRegularExpression('/hook_civicrm_caseChange/', $p->getOutput());
    $this->assertMatchesRegularExpression('/hook_civicrm_post/', $p->getOutput());
    $this->assertMatchesRegularExpression('/civi.token.eval/', $p->getOutput());
  }

  public function testName() {
    $p = Process::runOk($this->cv("debug:event-dispatcher hook_civicrm_caseChange"));
    $this->assertMatchesRegularExpression('/hook_civicrm_caseChange/', $p->getOutput());
    $this->assertDoesNotMatchRegularExpression('/hook_civicrm_post/', $p->getOutput());
    $this->assertDoesNotMatchRegularExpression('/civi.token.eval/', $p->getOutput());
  }

  public function testRegExp() {
    $p = Process::runOk($this->cv("debug:event-dispatcher /^hook/"));
    $this->assertMatchesRegularExpression('/hook_civicrm_caseChange/', $p->getOutput());
    $this->assertMatchesRegularExpression('/hook_civicrm_post/', $p->getOutput());
    $this->assertDoesNotMatchRegularExpression('/civi.token.eval/', $p->getOutput());
  }

}
