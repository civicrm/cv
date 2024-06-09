<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group angular
 */
class AngularModuleListCommandTest extends \Civi\Cv\CivilTestCase {

  /**
   * List extensions using a regular expression.
   */
  public function testGetRegex() {
    $p = Process::runOk($this->cv('ang:module:list'));
    $this->assertMatchesRegularExpression(';crmUi.*civicrm/a.*crmResource;', $p->getOutput());

    $p = Process::runOk($this->cv('ang:module:list ";crm;"'));
    $this->assertMatchesRegularExpression(';crmUi.*civicrm/a.*crmResource;', $p->getOutput());

    $p = Process::runOk($this->cv('ang:module:list ";foo;"'));
    $this->assertDoesNotMatchRegularExpression(';crmUi.*civicrm/a.*crmResource;', $p->getOutput());
  }

}
