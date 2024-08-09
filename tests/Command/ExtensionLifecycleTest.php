<?php
namespace Civi\Cv\Command;

use Civi\Cv\Exception\ProcessErrorException;
use Civi\Cv\Util\Process;

/**
 * @group std
 * @group ext
 */
class ExtensionLifecycleTest extends \Civi\Cv\CivilTestCase {

  const EXAMPLE_DOWNLOAD_URL = 'https://download.civicrm.org/cv/org.example.cvtest-20161227.zip';

  private static $first = TRUE;

  private $tmpDir;

  public function setUp(): void {
    parent::setup();
    $this->tmpDir = $this->getExampleDir() . '/vendor/cvtest';

    if (self::$first) {
      self::$first = FALSE;
      $this->cleanup();
    }

    $this->removeDir($this->tmpDir);
    foreach (array('/vendor', '/vendor/cvtest') as $part) {
      if (!is_dir($this->getExampleDir() . $part)) {
        mkdir($this->getExampleDir() . $part);
      }
    }
  }

  public function tearDown(): void {
    $this->cleanup();
    parent::tearDown();
  }

  /**
   * Enable, disable, uninstall a local extension (using its full
   * name).
   */
  public function testLifecycleWithFullKeys() {
    $cvTestZip = $this->makeCvTestZip();
    $this->extractZip($cvTestZip, $this->tmpDir);

    // Make sure we start in a clean environment without the extension
    $this->assertEquals(NULL, $this->getCvTestPath(), 'org.example.cvtest should not yet exist in the test build');
    Process::runFail($this->cvTestEnabled());

    // Activate and use the extension
    Process::runOk($this->cv("ext:enable -r org.example.cvtest"));
    $p = Process::runOk($this->cvTestEnabled());
    $result = json_decode($p->getOutput(), 1);
    $this->assertEquals('yes', $result['why']);

    // Cleanup and ensure we cleaned up.
    Process::runOk($this->cv("ext:disable org.example.cvtest"));
    Process::runFail($this->cvTestEnabled());
    Process::runOk($this->cv("ext:uninstall org.example.cvtest"));
    Process::runFail($this->cvTestEnabled());
  }

  /**
   * Enable, disable, uninstall a local extension (using its short
   * name).
   */
  public function testLifecycleWithShortNames() {
    $cvTestZip = $this->makeCvTestZip();
    $this->extractZip($cvTestZip, $this->tmpDir);

    // Make sure we start in a clean environment without the extension
    $this->assertEquals(NULL, $this->getCvTestPath(), 'org.example.cvtest should not yet exist in the test build');
    Process::runFail($this->cvTestEnabled());

    // Activate and use the extension
    Process::runOk($this->cv("ext:enable -r cvtest"));
    $p = Process::runOk($this->cvTestEnabled());
    $result = json_decode($p->getOutput(), 1);
    $this->assertEquals('yes', $result['why']);

    // Cleanup and ensure we cleaned up.
    Process::runOk($this->cv("ext:disable cvtest"));
    Process::runFail($this->cvTestEnabled());
    Process::runOk($this->cv("ext:uninstall cvtest"));
    Process::runFail($this->cvTestEnabled());
  }

  /**
   * Download an extension using an explicit URL.
   */
  public function testDownloadWithExplicitUrl() {
    $p = Process::runOk($this->cv("ev 'return [CIVICRM_UF, CRM_Utils_System::version()];'"));
    $envCheck = json_decode($p->getOutput(), 1);
    if ($envCheck[0] === 'WordPress' && version_compare($envCheck[1], '5.52.alpha1', '<')) {
      // The test passes on wp-demo@5.52 but fails @5.51. The difference is probably #23768.
      $this->markTestSkipped('Not supported on wp-demo with v5.51');
    }
    // Make sure we start in a clean environment without the extension
    $this->assertEquals(NULL, $this->getCvTestPath(), 'org.example.cvtest should not yet exist in the test build');
    Process::runFail($this->cvTestEnabled());

    // Activate and use the extension
    $extSpec = escapeshellarg("org.example.cvtest@" . self::EXAMPLE_DOWNLOAD_URL);
    Process::runOk($this->cv("ext:download -f $extSpec"));

    $p = Process::runOk($this->cvTestEnabled());
    $result = json_decode($p->getOutput(), 1);
    $this->assertEquals('yes', $result['why']);

    // Cleanup and ensure we cleaned up.
    Process::runOk($this->cv("ext:disable cvtest"));
    Process::runFail($this->cvTestEnabled());
    Process::runOk($this->cv("ext:uninstall cvtest"));
    Process::runFail($this->cvTestEnabled());
  }

  /**
   * Run a DB upgrade.
   *
   * We don't have any way to ensure that every extension runs a
   * proper upgrade sequence, but we can at least check if the command
   * executes.
   */
  public function testUpgradeDb() {
    $p = Process::runOk($this->cv('ext:upgrade-db'));
    $this->assertMatchesRegularExpression(';Applying database upgrades from extensions;', $p->getOutput());
  }

  /**
   * Prepare a subcommand which only succeeds if org.example.cvtest is
   * enabled.
   *
   * @return \Symfony\Component\Process\Process
   */
  public function cvTestEnabled() {
    // A small snippet of PHP code which only executes if `org.example.cvtest` is enabled.
    $doIWork = escapeshellarg('return cvtest_doiwork();');
    return $this->cv("ev $doIWork");
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

  protected function extractZip($zipFile, $path) {
    Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline(
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

  /**
   * Lookup the path to org.example.cvtest in the site's extension system.
   */
  protected function getCvTestPath() {
    $checkPath = 'try {return array("cvtest" => CRM_Extension_System::singleton()->getFullContainer()->getPath("org.example.cvtest"));} catch (CRM_Extension_Exception_MissingException $e) {return array();}';
    $process = Process::runOk($this->cv('ev ' . escapeshellarg($checkPath)));
    $data = json_decode($process->getOutput(), 1);
    if (!empty($data['cvtest']) && file_exists($data['cvtest'])) {
      return $data['cvtest'];
    }
    else {
      return NULL;
    }
  }

  protected function cleanup() {
    $disablePhp = 'civicrm_api3("Extension", "disable", array("key" => "org.example.cvtest"));';
    $disablePhp .= 'civicrm_api3("Extension", "uninstall", array("key" => "org.example.cvtest"));';
    $this->cv('ev ' . escapeshellarg($disablePhp))->run();
    $this->removeDir($this->tmpDir);

    try {
      $cvTestPath = $this->getCvTestPath();
    }
    catch (ProcessErrorException $e) {
      $cvTestPath = NULL;
    }
    if ($cvTestPath) {
      $this->removeDir($cvTestPath);
    }
  }

}
