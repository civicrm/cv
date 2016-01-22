<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

class BootlCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testBootDefault() {
    $phpBoot = Process::runOk($this->cv("php:boot"));
    $this->assertRegExp(';CIVICRM_SETTINGS_PATH;', $phpBoot->getOutput());

    $helloPhp = escapeshellarg($phpBoot->getOutput()
      . 'printf("count is %s\n", CRM_Core_DAO::singleValueQuery("select count(*) from civicrm_contact"));'
    );
    $phpRun = Process::runOk(new \Symfony\Component\Process\Process("php -r $helloPhp"));
    $this->assertRegExp('/^count is [0-9]+$/', $phpRun->getOutput());
  }

  public function testBootClassLoader() {
    $phpBoot = Process::runOk($this->cv("php:boot --level=classloader"));
    $this->assertRegExp(';ClassLoader;', $phpBoot->getOutput());

    // In the classloader level, config vals like CIVICRM_DSN are not loaded.
    $helloPhp = escapeshellarg($phpBoot->getOutput()
      . '$x=array("a"=>defined("CIVICRM_DSN") ? "yup" : "nope");'
      . 'printf("phpr says %s\n", CRM_Utils_Array::value("a",$x));'
    );
    $phpRun = Process::runOk(new \Symfony\Component\Process\Process("php -r $helloPhp"));
    $this->assertRegExp('/^phpr says nope$/', $phpRun->getOutput());
  }

}
