<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

class ExtensionLifecycleTest extends \Civi\Cv\CivilTestCase {

  private $tmpDir;

  public function setup() {
    parent::setup();
    $this->tmpDir = $this->getExampleDir() . '/vendor/cvtest';
    $this->removeDir($this->tmpDir);
    foreach (array('/vendor', '/vendor/cvtest') as $part) {
      if (!is_dir($this->getExampleDir() . $part)) {
        mkdir($this->getExampleDir() . $part);
      }
    }
  }

  public function tearDown() {
    $this->cleanup();
    parent::tearDown();
  }

  public function testLifecycleWithFullKeys() {
    $cvTestZip = $this->makeCvTestZip();
    $this->extractZip($cvTestZip, $this->tmpDir);

    // A small snippet of PHP code which only executes if `org.example.cvtest` is enabled.
    $doIWork = escapeshellarg('return cvtest_doiwork();');

    // Make sure we start in a clean environment without the extension
    Process::runFail($this->cv("ev $doIWork"));

    // Activate and use the extension
    Process::runOk($this->cv("ext:enable -r org.example.cvtest"));
    $p = Process::runOk($this->cv("ev $doIWork"));
    $result = json_decode($p->getOutput(), 1);
    $this->assertEquals('yes', $result['why']);

    // Cleanup and ensure we cleaned up.
    Process::runOk($this->cv("ext:disable org.example.cvtest"));
    Process::runFail($this->cv("ev $doIWork"));
    Process::runOk($this->cv("ext:uninstall org.example.cvtest"));
    Process::runFail($this->cv("ev $doIWork"));
  }

  public function testLifecycleWithShortNames() {
    $cvTestZip = $this->makeCvTestZip();
    $this->extractZip($cvTestZip, $this->tmpDir);

    $doIWork = escapeshellarg('return cvtest_doiwork();');

    // Make sure we start in a clean environment without the extension
    Process::runFail($this->cv("ev $doIWork"));

    // Activate and use the extension
    Process::runOk($this->cv("ext:enable -r cvtest"));
    $p = Process::runOk($this->cv("ev $doIWork"));
    $result = json_decode($p->getOutput(), 1);
    $this->assertEquals('yes', $result['why']);

    // Cleanup and ensure we cleaned up.
    Process::runOk($this->cv("ext:disable cvtest"));
    Process::runFail($this->cv("ev $doIWork"));
    Process::runOk($this->cv("ext:uninstall cvtest"));
    Process::runFail($this->cv("ev $doIWork"));
  }

  /**
   * Make a zip file for the placeholder extension, `org.example.cvtest`.
   * @return string
   *   Path to zip file
   */
  protected function makeCvTestZip() {
    $cvTestSrc = dirname(__DIR__) . '/fixtures/org.example.cvtest';
    $makePhp = $cvTestSrc . DIRECTORY_SEPARATOR . 'make.php';
    $cvTestZip = $this->tmpDir . DIRECTORY_SEPARATOR . 'cvtest.zip';
    Process::runOk(new \Symfony\Component\Process\Process(
      escapeshellcmd($makePhp) . ' ' . escapeshellarg($cvTestZip),
      $cvTestSrc
    ));
    return $cvTestZip;
  }

  protected function extractZip($zipFile, $path) {
    Process::runOk(new \Symfony\Component\Process\Process(
      "unzip " . escapeshellarg($zipFile),
      $path
    ));
  }

  /**
   * @param string $dir
   */
  protected function removeDir($dir) {
    if (!empty($dir) && file_exists($dir) && is_dir($dir)) {
      exec("rm -rf " . escapeshellarg($dir));
    }
  }

  protected function cleanup() {
    $disablePhp = 'civicrm_api3("Extension", "disable", array("key" => "org.example.cvtest"));';
    $disablePhp .= 'civicrm_api3("Extension", "uninstall", array("key" => "org.example.cvtest"));';
    $this->cv('ev ' . escapeshellarg($disablePhp))->run();
    $this->removeDir($this->tmpDir);
  }

}
