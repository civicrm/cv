<?php

namespace Civi\Cv\Plugin;

/**
 * @group std
 */
class AliasPluginTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  protected function cv($command) {
    $process = parent::cv($command);
    $process->setEnv(['CV_PLUGIN_PATH' => preg_replace(';\.php$;', '', __FILE__)]);
    return $process;
  }

  public function testDummyAlias() {
    $output = $this->cvOk('@dummy ext:list -Li');
    $this->assertMatchesRegularExpression(";^DUMMY: '.*/(cv|cv.phar)' --site-alias=dummy 'ext:list' -Li;", $output);
  }

  public function testUnknownAlias() {
    $output = $this->cvFail('@eldorado ext:list -Li');
    $this->assertMatchesRegularExpression('/Unknown site alias: eldorado/', $output);
  }

}
