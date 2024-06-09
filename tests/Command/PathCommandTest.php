<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class PathCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testNothing() {
    $p = Process::runFail($this->cv('path'));
    $this->assertMatchesRegularExpression('/Must use -x, -c, or -d/', $p->getErrorOutput());
  }

  public function testExtPaths() {
    $vars = $this->cvJsonOk('vars:show');
    $this->assertTrue(is_dir($vars['CIVI_CORE']));
    $this->assertTrue(file_exists($vars['CIVI_CORE']));

    // Try "cv path -x <extension>".
    $plain = rtrim($this->cvOk("path -x civicrm"), "\n");
    $this->assertEquals(rtrim($vars['CIVI_CORE'], '/'), $plain);

    $plain = rtrim($this->cvOk("path -x civicrm/"), "\n");
    $this->assertEquals($vars['CIVI_CORE'], $plain);

    $plain = rtrim($this->cvOk("path -x civicrm/packages"), "\n");
    $this->assertEquals($vars['CIVI_CORE'] . 'packages', $plain);

    $json = $this->cvJsonOk("path -x civicrm --out=json");
    $this->assertEquals('ext', $json[0]['type']);
    $this->assertEquals('civicrm', $json[0]['expr']);
    $this->assertEquals(rtrim($vars['CIVI_CORE'], '/'), $json[0]['value']);
  }

  public function testDynamicExprPaths() {
    $vars = $this->cvJsonOk('vars:show');
    $this->assertTrue(is_dir($vars['CIVI_CORE']));
    $this->assertTrue(file_exists($vars['CIVI_CORE']));

    if (version_compare($vars['CIVI_VERSION'], '4.7.0', '<')) {
      $this->markTestSkipped('"cv path -d" requires v4.7+');
    }

    $plain = rtrim($this->cvOk("path -d '[civicrm.root]'"), "\n");
    $this->assertEquals(rtrim($vars['CIVI_CORE'], '/'), $plain);

    $plain = rtrim($this->cvOk("path -d '[civicrm.root]/'"), "\n");
    $this->assertEquals($vars['CIVI_CORE'], $plain);

    $plain = rtrim($this->cvOk("path -d '[civicrm.root]/packages'"), "\n");
    $this->assertEquals($vars['CIVI_CORE'] . 'packages', $plain);

    $json = $this->cvJsonOk("path -d '[civicrm.root]/packages/DB.php' --out=json");
    $this->assertEquals('dynamic', $json[0]['type']);
    $this->assertEquals('[civicrm.root]/packages/DB.php', $json[0]['expr']);
    $this->assertEquals($vars['CIVI_CORE'] . 'packages/DB.php', $json[0]['value']);
  }

  public function testConfigPaths() {
    $vars = $this->cvJsonOk('vars:show');
    $this->assertTrue(is_dir($vars['CIVI_CORE']));
    $this->assertTrue(file_exists($vars['CIVI_CORE']));

    $mandatorySettingNames = array(
      'configAndLogDir',
      'extensionsDir',
      'imageUploadDir',
      'templateCompileDir',
      'uploadDir',
    );
    foreach ($mandatorySettingNames as $settingName) {
      $plain = rtrim($this->cvOk("path -c $settingName"), "\n");
      $this->assertTrue(file_exists($plain) && is_dir($plain), "Check $settingName");
    }

    $optionalSettingNames = array(
      'customFileUploadDir',
      'customPHPPathDir',
      'customTemplateDir',
      'templateCompileDir/en_US',
    );
    foreach ($optionalSettingNames as $settingName) {
      $plain = rtrim($this->cvOk("path -c $settingName"), "\n");
      $this->assertTrue((file_exists($plain) && is_dir($plain)) || empty($plain), "Check $settingName");
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

}
