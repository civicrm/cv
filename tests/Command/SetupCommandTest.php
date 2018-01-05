<?php
namespace Civi\Cv\Command;

use Civi\Cv\CvTestTrait;
use Civi\Cv\Exception\ProcessErrorException;
use Civi\Cv\Util\Process;

/**
 * @group setup
 */
class SetupCommandTest extends \PHPUnit_Framework_TestCase {

  use CvTestTrait;

  protected $build;
  protected $buildName;
  protected $originalCwd;

  const CACHE_TTL = 21600;
  const TIMEOUT = 600;

  public function getTestCases() {
    $cases = [];
    // $cases[] = [
    //   'backdrop-empty',
    //   ['modules' => 'https://download.civicrm.org/latest/civicrm-RC-backdrop.tar.gz'],
    // ];
    // $cases[] = [
    //   'drupal-empty',
    //   ['sites/all/modules' => 'https://download.civicrm.org/latest/civicrm-RC-drupal.tar.gz'],
    // ];
    $cases[] = [
      'wp-empty',
      ['wp-content/plugins' => 'https://download.civicrm.org/latest/civicrm-RC-wordpress.zip'],
    ];
    return $cases;
  }

  public function setup() {
    foreach (array('civibuild', 'cv') as $cmd) {
      if (Process::findCommand($cmd) === NULL) {
        $this->markTestSkipped("The SetupCommandTest requires $cmd to be available in the PATH.");
      }
    }
    $this->originalCwd = getcwd();
    $this->buildName = 'tmpcvsetup' . rand(0, 100000);
    $this->build = NULL;
    parent::setup();
  }

  public function tearDown() {
    chdir($this->originalCwd);
    if (!empty($this->build['CMS_ROOT'])) {
      $this->removeDir($this->build['CMS_ROOT']);
    }
    Process::runOk($this->proc("amp cleanup"));
    parent::tearDown();
  }

  /**
   * @param string $buildType
   *   Ex: 'drupal-empty'.
   * @param array $downloads
   *   Ex: ['my/rel/path' => 'http://example/foo.zip']
   * @dataProvider getTestCases
   */
  public function testSetup($buildType, $downloads) {
    $createResult = Process::runOk($this->proc(
      sprintf('civibuild create %s --type %s', escapeshellarg($this->buildName), escapeshellarg($buildType))
    ));
    $this->build = $this->parseCivibuild($createResult->getOutput());
    chdir($this->build['CMS_ROOT']);

    foreach ($downloads as $path => $url) {
      Process::runOk($this->proc(
        sprintf('extract-url --cache-ttl %d %s=%s', self::CACHE_TTL, escapeshellarg($path), escapeshellarg($url))
      ));
    }

    // $this->cvFail("ev 'return CRM_Utils_System::version();'");

    $this->cvOk('core:setup -f');

    $result = $this->cvJsonOk("ev 'return CRM_Utils_System::version();'");
    $this->assertRegExp('/^[0-9\.]+$/', $result);
    $this->assertTrue(version_compare($result, '4.6.0', '>='));
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
   * @param string $text
   *   Output from civibuild.
   * @return array
   *   Ex: ['CMS_ROOT' => '/var/www', 'CMS_URL' => 'http://mybuild.localhost']
   */
  protected function parseCivibuild($text) {
    $values = array();
    $lines = explode("\n", $text);
    foreach ($lines as $line) {
      if (preg_match(';^ - (CMS_ROOT|CMS_URL|ADMIN_USER|DEMO_USER): (.*);', $line, $matches)) {
        $key = trim($matches[1], " \t\r\n");
        $value = trim($matches[2], " \t\r\n");
        $values[$key] = $value;
      }
    }
    return $values;
  }

  protected function proc($commandline, $cwd = NULL, array $env = NULL, $input = NULL, $timeout = self::TIMEOUT, array $options = array()) {
    $p = new \Symfony\Component\Process\Process($commandline, $cwd, $env, $input, $timeout, $options);
    return $p;
  }

}
