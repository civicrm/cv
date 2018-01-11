<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class FlushCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testFlush() {
    $p = Process::runOk($this->cv("flush"));
    $this->assertRegExp('/Flushing/', $p->getOutput());
  }

}
