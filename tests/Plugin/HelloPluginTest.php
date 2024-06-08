<?php
namespace Civi\Cv\Plugin;

/**
 * @group std
 */
class HelloPluginTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  protected function cv($command) {
    $process = parent::cv($command);
    $process->setEnv(['CV_PLUGIN_PATH' => preg_replace(';\.php$;', '', __FILE__)]);
    return $process;
  }

  public function testRun() {
    $output = $this->cvOk('hello');
    $this->assertMatchesRegularExpression('/^Hello there/', $output);
  }

}
