<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group angular
 */
class AngularHtmlListCommandTest extends \Civi\Cv\CivilTestCase {

  /**
   * List extensions using a regular expression.
   */
  public function testGetRegex() {
    $p = Process::runOk($this->cv('ang:html:list'));
    $this->assertRegexp(';crmUi/field.html;', $p->getOutput());

    // matches key
    $p = Process::runOk($this->cv('ang:html:list crmUi'));
    $this->assertRegexp(';crmUi/field.html;', $p->getOutput());

    // matches name
    $p = Process::runOk($this->cv('ang:html:list ";field;"'));
    $this->assertRegexp(';crmUi/field.html;', $p->getOutput());

    // matches name
    $p = Process::runOk($this->cv('ang:html:list crmAttachment'));
    $this->assertNotRegexp(';crmUi/field.html;', $p->getOutput());
  }

  /**
   * Get the extension data in an alternate format, eg JSON.
   */
  public function testGetJson() {
    $p = Process::runOk($this->cv('ang:html:list crmUi/field.html --out=json'));
    $data = json_decode($p->getOutput(), 1);
    $this->assertEquals(1, count($data));
    $this->assertEquals('crmUi/field.html', $data[0]['file']);
  }

}
