<?php
namespace Civi\Cv\Command;

class ShowCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testShowJson() {
    $p = $this->cv("show");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertNotEmpty($data['CIVICRM_SETTINGS_PATH']);
    $this->assertNotEmpty($data['civicrm']['root']['path']);
    $this->assertTrue(is_dir($data['civicrm']['root']['path']));
    $this->assertNotEmpty($data['cms']['root']['url']);
    $this->assertTrue(!isset($data['buildkit']));
    $this->assertRegExp('/^([0-9]|alpha|beta|\.)+$/', $data['VERSION']);
  }

  public function testShowJsonBuildkit() {
    $p = $this->cv("show --buildkit");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertNotEmpty($data['CIVICRM_SETTINGS_PATH']);
    $this->assertNotEmpty($data['civicrm']['root']['path']);
    $this->assertTrue(is_dir($data['civicrm']['root']['path']));
    $this->assertNotEmpty($data['cms']['root']['url']);
    $this->assertNotEmpty($data['buildkit']['ADMIN_USER']);
    $this->assertRegExp('/^([0-9]|alpha|beta|\.)+$/', $data['VERSION']);
  }

}
