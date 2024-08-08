<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group php
 */
class BootCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testBootFull() {
    $phpBoot = Process::runOk($this->cv("php:boot --level=full"));
    $this->assertMatchesRegularExpression(';CIVICRM_SETTINGS_PATH;', $phpBoot->getOutput());

    $helloPhp = escapeshellarg($phpBoot->getOutput()
      . 'printf("count is %s\n", CRM_Core_DAO::singleValueQuery("select count(*) from civicrm_contact"));'
      . 'printf("my admin is %s\n", $GLOBALS["_CV"]["ADMIN_USER"]);'
    );
    $phpRun = Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline("php -r $helloPhp"));
    $this->assertMatchesRegularExpression('/^count is [0-9]+\n/', $phpRun->getOutput());
    $this->assertMatchesRegularExpression('/my admin is \w+\n/', $phpRun->getOutput());
  }

  public function testBootCmsFull() {
    $phpBoot = Process::runOk($this->cv("php:boot --level=cms-full"));
    $this->assertMatchesRegularExpression(';BEGINPHP;', $phpBoot->getOutput());
    $this->assertMatchesRegularExpression(';ENDPHP;', $phpBoot->getOutput());

    $helloPhp = escapeshellarg($phpBoot->getOutput()
      . 'printf("count is %s\n", CRM_Core_DAO::singleValueQuery("select count(*) from civicrm_contact"));'
      . 'printf("my admin is %s\n", $GLOBALS["_CV"]["ADMIN_USER"]);'
    );
    $phpRun = Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline("php -r $helloPhp"));
    $this->assertMatchesRegularExpression('/^count is [0-9]+\n/', $phpRun->getOutput());
    $this->assertMatchesRegularExpression('/my admin is \w+\n/', $phpRun->getOutput());
  }

  public function testBootClassLoader() {
    $phpBoot = Process::runOk($this->cv("php:boot --level=classloader"));
    $this->assertMatchesRegularExpression(';ClassLoader;', $phpBoot->getOutput());

    // In the classloader level, config vals like CIVICRM_DSN are not loaded.
    $helloPhp = escapeshellarg($phpBoot->getOutput()
      . '$x=array("a"=>defined("CIVICRM_DSN") ? "yup" : "nope");'
      . 'printf("phpr says %s\n", CRM_Utils_Array::value("a",$x));'
    );
    $phpRun = Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline("php -r $helloPhp"));
    $this->assertMatchesRegularExpression('/^phpr says nope$/', $phpRun->getOutput());
  }

  public function testBootTest() {
    $phpBoot = Process::runOk($this->cv("php:boot --test"));
    $this->assertMatchesRegularExpression(';CIVICRM_SETTINGS_PATH;', $phpBoot->getOutput());

    $helloPhp = escapeshellarg($phpBoot->getOutput()
      . 'echo CIVICRM_UF;'
    );
    $phpRun = Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline("php -ddisplay_errors=1 -r $helloPhp"));
    $this->assertMatchesRegularExpression('/UnitTests/i', $phpRun->getOutput());
  }

}
