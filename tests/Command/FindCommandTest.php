<?php
namespace Civi\Cv\Command;

class FindCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testFindJson() {
    $p = $this->cv("find --json");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertNotEmpty($data['CIVICRM_SETTINGS_PATH']);
    $this->assertNotEmpty($data['civicrm']['root']['path']);
    $this->assertTrue(is_dir($data['civicrm']['root']['path']));
    $this->assertNotEmpty($data['civicrm']['root']['url']);
    $this->assertTrue(!isset($data['buildkit']));
  }

  public function testFindJsonBuildkit() {
    $p = $this->cv("find --json --buildkit");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertNotEmpty($data['CIVICRM_SETTINGS_PATH']);
    $this->assertNotEmpty($data['civicrm']['root']['path']);
    $this->assertTrue(is_dir($data['civicrm']['root']['path']));
    $this->assertNotEmpty($data['civicrm']['root']['url']);
    $this->assertNotEmpty($data['buildkit']['ADMIN_USER']);
  }


  public function testFindPhp() {
    $p = $this->cv("find --php");
    $p->run();
    $this->assertRegExp('/CIVICRM_SETTINGS_PATH.*/', $p->getOutput());
  }

}
