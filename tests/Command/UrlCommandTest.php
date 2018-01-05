<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @group std
 */
class UrlCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testSingleRoute() {
    $url = escapeshellarg('civicrm/a/#/mailing/new?angularDebug=1&foo=bar');
    $fullUrl = $this->cvJsonOk("url $url");
    $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_HOST));
    $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_SCHEME));
    $this->assertRegExp(':angularDebug=1:', $fullUrl);
    $this->assertRegExp(':foo=bar:', $fullUrl);
    $this->assertRegExp(':/mailing/new:', $fullUrl);
  }

  public function testMultipleRoute() {
    $url = escapeshellarg('civicrm/a/#/mailing/new?angularDebug=1&foo=bar');
    $urlTable = $this->cvJsonOk("url --tabular $url $url");
    for ($i = 0; $i < 2; $i++) {
      $fullUrl = $urlTable[$i]['value'];
      $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_HOST));
      $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_SCHEME));
      $this->assertRegExp(':angularDebug=1:', $fullUrl);
      $this->assertRegExp(':foo=bar:', $fullUrl);
      $this->assertRegExp(':/mailing/new:', $fullUrl);
    }
  }

  public function testOutput() {
    $output = $this->cvFail('url -x. -c extensionsURL --out=json');
    $this->assertRegExp(';specify --tabular;', $output);

    $output = $this->cvJsonOk('url -x. -c extensionsURL --out=json --tabular');
    $this->assertEquals(2, count($output));
    $this->assertRegExp(';https?://.*;', $output[0]['value']);
    $this->assertRegExp(';https?://.*;', $output[1]['value']);

    $output = explode("\n", trim($this->cvOk('url -x. -c extensionsURL --out=list')));
    $this->assertEquals(2, count($output));
    $this->assertRegExp(';https?://.*;', $output[0]);
    $this->assertRegExp(';https?://.*;', $output[1]);

    $output = $this->cvJsonOk('url -x. --out=json');
    $this->assertRegExp(';https?://.*;', $output);
  }

  public function testExtPaths() {
    $plain = rtrim($this->cvJsonOk("url -x civicrm"), "\n");
    $this->assertRegExp(';https?://.*/civicrm$;', $plain);

    $plain = rtrim($this->cvJsonOk("url -x civicrm/"), "\n");
    $this->assertRegExp(';https?://.*/civicrm/$;', $plain);

    $plain = rtrim($this->cvJsonOk("url -x civicrm/packages"), "\n");
    $this->assertRegExp(';https?://.*/civicrm/packages$;', $plain);
  }

  public function testDynamicExprPaths() {
    $vars = $this->cvJsonOk('vars:show');
    if (version_compare($vars['CIVI_VERSION'], '4.7.0', '<')) {
      $this->markTestSkipped('"cv path -d" requires v4.7+');
    }

    $plain = rtrim($this->cvJsonOk("url -d '[civicrm.root]'"), "\n");
    $this->assertRegExp(';https?://.*/civicrm$;', $plain);

    $plain = rtrim($this->cvJsonOk("url -d '[civicrm.root]/'"), "\n");
    $this->assertRegExp(';https?://.*/civicrm/$;', $plain);

    $plain = rtrim($this->cvJsonOk("url -d '[civicrm.root]/packages'"), "\n");
    $this->assertRegExp(';https?://.*/civicrm/packages$;', $plain);
  }

  public function testConfigPaths() {
    $vars = $this->cvJsonOk('vars:show');
    $this->assertTrue(is_dir($vars['CIVI_CORE']));
    $this->assertTrue(file_exists($vars['CIVI_CORE']));

    $mandatorySettingNames = array(
      'extensionsURL',
      'imageUploadURL',
      'userFrameworkBaseURL',
      'userFrameworkResourceURL',
    );
    foreach ($mandatorySettingNames as $settingName) {
      $plain = rtrim($this->cvOk("path -c $settingName"), "\n");
      $this->assertRegExp(';^https?://.*;', $plain, "Check $settingName");
    }

    $optionalSettingNames = array(
      'customCSSURL',
    );
    foreach ($optionalSettingNames as $settingName) {
      $plain = rtrim($this->cvOk("path -c $settingName"), "\n");
      $this->assertRegExp(';^(|https?://.*);', $plain, "Check $settingName");
    }
  }

  public function testExtDot() {
    $this->assertEquals(
      $this->cvOk('url -c extensionsURL'),
      $this->cvOk('url -x.')
    );
    $this->assertEquals(
      $this->cvOk('url -c extensionsURL'),
      $this->cvOk('url -x .')
    );
  }

}
