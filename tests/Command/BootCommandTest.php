<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

class BootlCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testUrl() {
    $phpBoot = Process::runOk($this->cv("php-boot"));
    $this->assertRegExp(';CIVICRM_SETTINGS_PATH;', $phpBoot->getOutput());

    $helloPhp = escapeshellarg($phpBoot->getOutput() . 'printf("phpr says version is %s\n", CRM_Utils_System::version());');
    $phpRun = Process::runOk(new \Symfony\Component\Process\Process("php -r $helloPhp"));
    $this->assertRegExp('/^phpr says version is [0-9a-z\.]+$/', $phpRun->getOutput());
  }

}
