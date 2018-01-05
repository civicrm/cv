<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class DebugContainerCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testShowAll() {
    $p = Process::runOk($this->cv("debug:container"));
    $this->assertRegExp('/cxn_reg_client.*Civi.Cxn.Rpc.RegistrationClient/', $p->getOutput());
    $this->assertRegExp('/civi_api_kernel.*Civi.API.Kernel/', $p->getOutput());
  }

  //  public function testName() {
  //    $p = Process::runOk($this->cv("debug:container hook_civicrm_caseChange"));
  //    $this->assertRegExp('/hook_civicrm_caseChange/', $p->getOutput());
  //    $this->assertNotRegExp('/hook_civicrm_post/', $p->getOutput());
  //    $this->assertNotRegExp('/civi.token.eval/', $p->getOutput());
  //  }

  //  public function testRegExp() {
  //    $p = Process::runOk($this->cv("debug:container /^hook/"));
  //    $this->assertRegExp('/hook_civicrm_caseChange/', $p->getOutput());
  //    $this->assertRegExp('/hook_civicrm_post/', $p->getOutput());
  //    $this->assertNotRegExp('/civi.token.eval/', $p->getOutput());
  //  }

}
