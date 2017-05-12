<?php
namespace Civi\Cv\Command;

class UpgradeGetCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testGetStable() {
    $p = $this->cv("upgrade:get --stability=stable");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data['url']), 'Looking for url in output: ' . $p->getOutput());
    $this->assertRegExp(';(\.zip|\.gz)$;', $data['url']);
    $this->assertRegExp('/^([0-9\.\-]|alpha|beta|master|x)+$/', $data['version']);
    $this->assertRegExp('/^[a-zA-Z0-9\.\-\_]+$/', $data['rev']);
  }

  public function testGetNightly() {
    $p = $this->cv("upgrade:get --stability=nightly");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data['url']), 'Looking for url in output: ' . $p->getOutput());
    $this->assertRegExp(';(\.zip|\.gz)$;', $data['url']);
    $this->assertRegExp('/^([0-9\.\-]|alpha|beta|master|x)+$/', $data['version']);
    $this->assertRegExp('/^[a-zA-Z0-9\.\-\_]+$/', $data['rev']);
  }

  public function testGetRc() {
    $p = $this->cv("upgrade:get --stability=rc");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data['url']), 'Looking for url in output: ' . $p->getOutput());
    $this->assertRegExp(';(\.zip|\.gz)$;', $data['url']);
    $this->assertRegExp('/^([0-9\.\-]|alpha|beta|master|x)+$/', $data['version']);
    $this->assertRegExp('/^[a-zA-Z0-9\.\-\_]+$/', $data['rev']);
  }

}
