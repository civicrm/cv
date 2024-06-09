<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group angular
 */
class AngularHtmlShowCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
    $p = Process::runOk($this->cv('php:eval \'return is_callable(array(Civi::service("angular"),"getRawPartials"));\''));
    $supported = json_decode($p->getOutput(), 1);
    if (!$supported) {
      $this->markTestSkipped("Cannot test: this version of CiviCRM does not support getRawPartials");
    }
  }

  /**
   * List extensions using a regular expression.
   */
  public function testShow() {

    $p = Process::runOk($this->cv('ang:html:show crmUi/field.html'));
    $this->assertMatchesRegularExpression(';div.*ng-transclude;', $p->getOutput());
  }

  /**
   * List extensions using a regular expression.
   */
  public function testShowRaw() {
    $p = Process::runOk($this->cv('ang:html:show --raw crmUi/field.html'));
    $this->assertMatchesRegularExpression(';div.*ng-transclude;', $p->getOutput());
  }

  /**
   * List extensions using a regular expression.
   */
  public function testShowDiff() {
    // We don't know enough about the system to be sure of what diff to
    // expect, but we can at least ensure that it doesn't crash.
    $p = Process::runOk($this->cv('ang:html:show --diff crmUi/field.html'));
    $this->assertNotEmpty($p->getOutput());
  }

}
