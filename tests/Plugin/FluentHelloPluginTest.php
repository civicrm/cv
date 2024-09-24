<?php
namespace Civi\Cv\Plugin;

/**
 * @group std
 */
class FluentHelloPluginTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  protected function cv($command) {
    $process = parent::cv($command);
    $process->setEnv(['CV_PLUGIN_PATH' => preg_replace(';\.php$;', '', __FILE__)]);
    return $process;
  }

  public function testRun() {
    $output = $this->cvOk('hello:normal');
    $this->assertMatchesRegularExpression('/Hey-yo world via parameter.*Hey-yo world via StyleInterface/s', $output);
  }

  public function testRunWithName() {
    $output = $this->cvOk('hello:normal Alice');
    $this->assertMatchesRegularExpression('/Hey-yo Alice via parameter.*Hey-yo Alice via StyleInterface/s', $output);
  }

  public function testRun_noboot() {
    $output = $this->cvOk('hello:noboot');
    $this->assertMatchesRegularExpression('/Hey-yo world via parameter.*Hey-yo world via StyleInterface/s', $output);
  }

  public function testRunWithName_noboot() {
    $output = $this->cvOk('hello:noboot Bob');
    $this->assertMatchesRegularExpression('/Hey-yo Bob via parameter.*Hey-yo Bob via StyleInterface/s', $output);
  }

}
