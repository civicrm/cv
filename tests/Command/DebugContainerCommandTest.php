<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class DebugContainerCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testShowAll() {
    $p = Process::runOk($this->cv("debug:container"));
    $this->assertMatchesRegularExpression('/(cxn_reg_client.*Civi.Cxn.Rpc.RegistrationClient|httpClient.*CRM_Utils_HttpClient|sql_triggers.*Civi.Core.SqlTrigger)/', $p->getOutput());
    $this->assertMatchesRegularExpression('/civi_api_kernel.*Civi.API.Kernel/', $p->getOutput());
  }

  //  public function testName() {
  //    $p = Process::runOk($this->cv("debug:container hook_civicrm_caseChange"));
  //    $this->assertMatchesRegularExpression('/hook_civicrm_caseChange/', $p->getOutput());
  //    $this->assertDoesNotMatchRegularExpression('/hook_civicrm_post/', $p->getOutput());
  //    $this->assertDoesNotMatchRegularExpression('/civi.token.eval/', $p->getOutput());
  //  }

  //  public function testRegExp() {
  //    $p = Process::runOk($this->cv("debug:container /^hook/"));
  //    $this->assertMatchesRegularExpression('/hook_civicrm_caseChange/', $p->getOutput());
  //    $this->assertMatchesRegularExpression('/hook_civicrm_post/', $p->getOutput());
  //    $this->assertDoesNotMatchRegularExpression('/civi.token.eval/', $p->getOutput());
  //  }

}
