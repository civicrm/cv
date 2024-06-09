<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class FlushCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testFlush() {
    $p = Process::runOk($this->cv("flush"));
    $this->assertMatchesRegularExpression('/Flushing/', $p->getOutput());
  }

}
