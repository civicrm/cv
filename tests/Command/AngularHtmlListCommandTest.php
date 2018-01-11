<?php
namespace Civi\Cv\Command;

use Civi\Cv\Exception\ProcessErrorException;
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

    $p = Process::runOk($this->cv('ang:html:list crmUi')); // matches key
    $this->assertRegexp(';crmUi/field.html;', $p->getOutput());

    $p = Process::runOk($this->cv('ang:html:list ";field;"')); // matches name
    $this->assertRegexp(';crmUi/field.html;', $p->getOutput());

    $p = Process::runOk($this->cv('ang:html:list crmAttachment')); // matches name
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
