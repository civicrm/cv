<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

class UrllCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testUrl() {
    $url = escapeshellarg('civicrm/a/#/mailing/new?angularDebug=1&foo=bar');
    $p = Process::runOk($this->cv("url $url"));
    $fullUrl = json_decode($p->getOutput());
    $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_HOST));
    $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_SCHEME));
    $this->assertRegExp(':angularDebug=1:', $fullUrl);
    $this->assertRegExp(':foo=bar:', $fullUrl);
    $this->assertRegExp(':/mailing/new:', $fullUrl);
  }

}
