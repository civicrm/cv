<?php
namespace Civi\Cv\Command;

/**
 * @group std
 */
class UrlCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testSingleRoute() {
    $url = escapeshellarg('civicrm/a/#/mailing/new?angularDebug=1&foo=bar');
    $fullUrl = $this->cvJsonOk("url $url");
    $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_HOST));
    $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_SCHEME));
    $this->assertMatchesRegularExpression(':angularDebug=1:', $fullUrl);
    $this->assertMatchesRegularExpression(':foo=bar:', $fullUrl);
    $this->assertMatchesRegularExpression(':/mailing/new:', $fullUrl);
  }

  public function testMultipleRoute() {
    $url = escapeshellarg('civicrm/a/#/mailing/new?angularDebug=1&foo=bar');
    $urlTable = $this->cvJsonOk("url --tabular $url $url");
    for ($i = 0; $i < 2; $i++) {
      $fullUrl = $urlTable[$i]['value'];
      $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_HOST));
      $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_SCHEME));
      $this->assertMatchesRegularExpression(':angularDebug=1:', $fullUrl);
      $this->assertMatchesRegularExpression(':foo=bar:', $fullUrl);
      $this->assertMatchesRegularExpression(':/mailing/new:', $fullUrl);
    }
  }

  public function testOutput() {
    $output = $this->cvFail('url -x. -c extensionsURL --out=json');
    $this->assertMatchesRegularExpression(';specify --tabular;', $output);

    $output = $this->cvJsonOk('url -x. -c extensionsURL --out=json --tabular');
    $this->assertEquals(2, count($output));
    $this->assertMatchesRegularExpression(';https?://.*;', $output[0]['value']);
    $this->assertMatchesRegularExpression(';https?://.*;', $output[1]['value']);

    $output = explode("\n", trim($this->cvOk('url -x. -c extensionsURL --out=list')));
    $this->assertEquals(2, count($output));
    $this->assertMatchesRegularExpression(';https?://.*;', $output[0]);
    $this->assertMatchesRegularExpression(';https?://.*;', $output[1]);

    $output = $this->cvJsonOk('url -x. --out=json');
    $this->assertMatchesRegularExpression(';https?://.*;', $output);
  }

  public function testExtPaths() {
    $plain = rtrim($this->cvJsonOk("url -x civicrm"), "\n");
    $this->assertMatchesRegularExpression(';https?://.*/civicrm($|/core$);', $plain);

    $plain = rtrim($this->cvJsonOk("url -x civicrm/"), "\n");
    $this->assertMatchesRegularExpression(';https?://.*/civicrm(/$|/core/$);', $plain);

    $plain = rtrim($this->cvJsonOk("url -x civicrm/packages"), "\n");
    $this->assertMatchesRegularExpression(';https?://.*/civicrm(/|/core/)packages$;', $plain);
  }

  public function testDynamicExprPaths() {
    $vars = $this->cvJsonOk('vars:show');
    if (version_compare($vars['CIVI_VERSION'], '4.7.0', '<')) {
      $this->markTestSkipped('"cv path -d" requires v4.7+');
    }

    $plain = rtrim($this->cvJsonOk("url -d '[civicrm.root]'"), "\n");
    $this->assertMatchesRegularExpression(';https?://.*/civicrm($|/\w+$);', $plain);

    $plain = rtrim($this->cvJsonOk("url -d '[civicrm.root]/'"), "\n");
    $this->assertMatchesRegularExpression(';https?://.*/civicrm(/$|/\w+/$);', $plain);

    $plain = rtrim($this->cvJsonOk("url -d '[civicrm.root]/packages'"), "\n");
    $this->assertMatchesRegularExpression(';https?://.*/civicrm(/|/core/)packages$;', $plain);

    $plain = rtrim($this->cvJsonOk("url -d '[civicrm.packages]'"), "\n");
    $this->assertMatchesRegularExpression(';https?://.*/civicrm/packages$;', $plain);
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
      $this->assertMatchesRegularExpression(';^https?://.*;', $plain, "Check $settingName");
    }

    $optionalSettingNames = array(
      'customCSSURL',
    );
    foreach ($optionalSettingNames as $settingName) {
      $plain = rtrim($this->cvOk("path -c $settingName"), "\n");
      $this->assertMatchesRegularExpression(';^(|https?://.*);', $plain, "Check $settingName");
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
