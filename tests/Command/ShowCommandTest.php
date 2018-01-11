<?php
namespace Civi\Cv\Command;

/**
 * @group std
 */
class ShowCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testShowJson() {
    $p = $this->cv("vars:show");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertRegExp('/^([0-9\.\-]|alpha|beta|master|x)+$/', $data['CIVI_VERSION']);
    $this->assertRegExp('/^([0-9\.\-]|alpha|beta|master|x)+$/', $data['CMS_VERSION']);
    $this->assertTrue(is_dir($data['CMS_ROOT']));
    $this->assertTrue(is_dir($data['CIVI_CORE']));
  }

}
