<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group ext
 */
class ExtensionBareDownloadTest extends \Civi\Cv\CivilTestCase {

  private static $first = TRUE;

  private $tmpDir;

  public function setUp(): void {
    parent::setup();
    $this->tmpDir = sys_get_temp_dir() . '/baredl';

    if (self::$first) {
      self::$first = FALSE;
      $this->cleanup();
    }

    $this->removeDir($this->tmpDir);
    mkdir($this->tmpDir);
    chdir($this->tmpDir);
  }

  public function tearDown(): void {
    parent::tearDown();
    $this->cleanup();
  }

  /**
   * Download an extension using an explicit URL.
   */
  public function testDownloadBare() {
    $toPath = $this->tmpDir . '/extracted-ext';

    $origFile = dirname(__DIR__) . '/fixtures/org.example.cvtest/info.xml';
    $finalFile = "$toPath/info.xml";
    $this->assertFalse(file_exists($finalFile), "File $finalFile should not yet exist.");

    $cvTestZip = $this->makeCvTestZip();
    $infoXmlPath = $this->makeDownloadManifest($origFile, 'file://' . $cvTestZip);

    $extSpecArg = escapeshellarg("@" . $infoXmlPath);
    $toArg = escapeshellarg($toPath);
    Process::runOk($this->cv("ext:download -b $extSpecArg --to=$toArg"));

    $this->assertTrue(file_exists($finalFile), "File $finalFile should exist.");
    $this->assertEquals(file_get_contents($origFile), file_get_contents($finalFile));
  }

  protected function makeDownloadManifest($origFile, $downloadUrl) {
    $xml = simplexml_load_string(file_get_contents($origFile));
    $xml->addChild('downloadUrl', $downloadUrl);
    $newFile = $this->tmpDir . DIRECTORY_SEPARATOR . 'fixme.xml';
    file_put_contents($newFile, $xml->saveXML());
    return $newFile;
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
    Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline(
      escapeshellcmd($makePhp) . ' ' . escapeshellarg($cvTestZip),
      $cvTestSrc
    ));
    return $cvTestZip;
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
    $this->removeDir($this->tmpDir);
  }

}
