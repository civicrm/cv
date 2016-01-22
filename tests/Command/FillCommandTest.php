<?php
namespace Civi\Cv\Command;

class FillCommandTest extends \Civi\Cv\CivilTestCase {

  public function testShowJson() {
    $tmpConfigFile = tempnam(sys_get_temp_dir(), 'cv-test-');

    $p = $this->cv("config:fill --file=/dev/stdin");
    $p->setInput(json_encode(array(
      'ADMIN_USER' => 'admin',
    )));
    $p->setEnv(array(
      'CV_CONFIG' => $tmpConfigFile,
    ));
    $p->run();

    $config = json_decode(file_get_contents($tmpConfigFile), 1);
    unlink($tmpConfigFile);

    $this->assertRegExp('/Please edit.*' . preg_quote($tmpConfigFile, '/') . '/', $p->getOutput());
    $this->assertNotEmpty($config);
    $this->assertNotEmpty($config['sites']);
    foreach ($config['sites'] as $path => $siteConfig) {
      $this->assertEquals('', $siteConfig['ADMIN_PASS']);
      $this->assertTrue(!isset($siteConfig['ADMIN_USER']));
    }
  }

}
