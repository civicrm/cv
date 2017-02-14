<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

class PathCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testPaths() {
    $vars = $this->cvJsonOk('vars:show');
    $this->assertTrue(is_dir($vars['CIVI_CORE']));
    $this->assertTrue(file_exists($vars['CIVI_CORE']));

    // Try "cv path -x <extension>".
    $plain = rtrim($this->cvOk("path -x civicrm"), "\n");
    $this->assertEquals($vars['CIVI_CORE'], $plain);
    $json = $this->cvJsonOk("path -x civicrm --out=json");
    $this->assertEquals('ext', $json[0]['type']);
    $this->assertEquals('civicrm', $json[0]['name']);
    $this->assertEquals($vars['CIVI_CORE'], $json[0]['value']);
  }

  public function testDynamicExprPaths() {
    $vars = $this->cvJsonOk('vars:show');
    $this->assertTrue(is_dir($vars['CIVI_CORE']));
    $this->assertTrue(file_exists($vars['CIVI_CORE']));

    if (version_compare($vars['CIVI_VERSION'], '4.7.0', '<')) {
      $this->markTestSkipped('"cv path -d" requires v4.7+');
    }

    $plain = rtrim($this->cvOk("path -d '[civicrm.root]'"), "\n");
    $this->assertEquals($vars['CIVI_CORE'], $plain);

    $plain = rtrim($this->cvOk("path -d '[civicrm.root]/packages'"), "\n");
    $this->assertEquals($vars['CIVI_CORE'] . 'packages/', $plain);

    $json = $this->cvJsonOk("path -d '[civicrm.root]/packages' --out=json");
    $this->assertEquals('dynamic', $json[0]['type']);
    $this->assertEquals('[civicrm.root]/packages', $json[0]['name']);
    $this->assertEquals($vars['CIVI_CORE'] . 'packages/', $json[0]['value']);
  }

  public function testConfigPaths() {
    $vars = $this->cvJsonOk('vars:show');
    $this->assertTrue(is_dir($vars['CIVI_CORE']));
    $this->assertTrue(file_exists($vars['CIVI_CORE']));

    $mandatorySettingNames = array(
      'customFileUploadDir',
      'extensionsDir',
      'imageUploadDir',
      'templateCompileDir',
      'uploadDir',
    );
    foreach ($mandatorySettingNames as $settingName) {
      $plain = rtrim($this->cvOk("path -c $settingName"), "\n");
      $this->assertTrue(file_exists($plain) && is_dir($plain));
    }

    $optionalSettingNames = array(
      'customPHPPathDir',
      'customTemplateDir',
    );
    foreach ($optionalSettingNames as $settingName) {
      $plain = rtrim($this->cvOk("path -c $settingName"), "\n");
      $this->assertTrue((file_exists($plain) && is_dir($plain)) || empty($plain));
    }
  }

  public function testExtDot() {
    $this->assertEquals(
      $this->cvOk('path -x.'),
      $this->cvOk('path -c extensionsDir')
    );
    $this->assertEquals(
      $this->cvOk('path -x .'),
      $this->cvOk('path -c extensionsDir')
    );
  }

  protected function cvOk($cmd) {
    $p = Process::runOk($this->cv($cmd));
    return $p->getOutput();
  }

  protected function cvJsonOk($cmd) {
    $p = Process::runOk($this->cv($cmd));
    return json_decode($p->getOutput(), 1);
  }

}
