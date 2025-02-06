<?php
namespace Civi\Cv\Command;

/**
 * @group std
 */
class StatusCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setup();
  }

  public function testStatus() {
    $p = $this->cv("status");
    $p->run();
    $data = $p->getOutput();
    $this->assertTrue((bool) preg_match('/| php .* | \d+\.\d+\./', $data));
  }

  public function testStatusJson() {
    $p = $this->cv("status --out=json");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue((bool) preg_match('/^\d+\.\d+\./', $data['civicrm']['value']));
    $this->assertTrue((bool) preg_match('/^\d+\.\d+\./', $data['php']['value']));
  }

}
