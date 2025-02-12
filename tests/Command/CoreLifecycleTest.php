<?php
namespace Civi\Cv\Command;

use Civi\Cv\CvTestTrait;
use Civi\Cv\Util\Process;

/**
 * Test the installation and uninstallation process.
 *
 * @group installer
 */
class CoreLifecycleTest extends \PHPUnit\Framework\TestCase {

  use CvTestTrait;

  protected $build;
  protected $buildName;
  protected $originalCwd;

  const CACHE_TTL = 21600;
  const TIMEOUT = 600;

  public function getTestCases() {
    $cases = [];
    $cases[] = [
      'backdrop-empty',
      ['modules' => 'https://download.civicrm.org/latest/civicrm-RC-backdrop.tar.gz'],
      'core:install -f --url=http://localhost',
      '',
    ];
    $cases[] = [
      'drupal-empty',
      ['sites/all/modules' => 'https://download.civicrm.org/latest/civicrm-RC-drupal.tar.gz'],
      'core:install -f --url=http://localhost',
    // 'drush -y en civicrm', // No longer needed -- FlushDrupal plugin autoenables.
      '',
    ];
    $cases[] = [
      'wp-empty',
      ['wp-content/plugins' => 'https://download.civicrm.org/latest/civicrm-RC-wordpress.zip'],
      'core:install -f',
      'wp plugin activate civicrm',
    ];
    return $cases;
  }

  public function setUp(): void {
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

  public function tearDown(): void {
    chdir($this->originalCwd);
    if (!empty($this->build['CMS_ROOT'])) {
      $this->removeDir($this->build['CMS_ROOT']);
    }
    Process::runOk($this->proc("amp cleanup"));
    parent::tearDown();
  }

  /**
   * Create an empty CMS build; install Civi; and remove it. At each step,
   * verify that Civi is (or is not) operational.
   *
   * @param string $buildType
   *   Ex: 'drupal-empty'.
   * @param array $downloads
   *   Ex: ['my/rel/path' => 'http://example/foo.zip']
   * @param string $installCmd
   *   Ex: 'core:install -f'
   * @param string $postInstallCmd
   *   Ex: 'drush -y en civicrm' or 'wp plugin activate civicrm'.
   * @dataProvider getTestCases
   */
  public function testStandardLifecycle($buildType, $downloads, $installCmd, $postInstallCmd) {
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

    // We've installed CMS -- but not Civi. Expect an error.
    $output = $this->cvFail("ev 'return CRM_Utils_System::version();'");
    $this->assertMatchesRegularExpression('/Failed to locate civicrm.settings.php/', $output);

    $output = Process::runDebug($this->cv('core:check-req --out=table'))->getOutput();
    $this->assertMatchesRegularExpression('/Found.*civicrm-core/', $output);
    $this->assertMatchesRegularExpression('/Found.*civicrm-setup/', $output);
    $this->assertMatchesRegularExpression('/| *info *| *lang/', $output);

    $output = $this->cvOk($installCmd);
    $this->assertMatchesRegularExpression('/Creating file.*civicrm.settings.php/', $output);
    $this->assertMatchesRegularExpression('/Creating civicrm_\* database/', $output);

    if ($postInstallCmd) {
      Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline($postInstallCmd));
    }

    // We've installed CMS+Civi. All should be well.
    $result = $this->cvJsonOk("ev 'return CRM_Utils_System::version();'");
    $this->assertMatchesRegularExpression('/^[0-9]([0-9\.]|alpha|beta)+$/', $result);
    $this->assertTrue(version_compare($result, '4.6.0', '>='));

    // The upgrade command doesn't have much to do, but let's make sure it doesn't crash.
    $output = $this->cvOk("upgrade:db");
    $this->assertMatchesRegularExpression('/Found CiviCRM database version ([0-9\.]|alpha|beta)+/', $output);
    $this->assertMatchesRegularExpression('/Found CiviCRM code version ([0-9\.]|alpha|beta)+/', $output);
    $this->assertMatchesRegularExpression('/Have a nice day/', $output);

    $output = $this->cvOk('core:uninstall -f');
    $this->assertMatchesRegularExpression('/Removing .*civicrm.settings.php/', $output);
    $this->assertMatchesRegularExpression('/Removing civicrm_\*/', $output);

    // We've n o longer got Civi - expect an error.
    $output = $this->cvFail("ev 'return CRM_Utils_System::version();'");
    $this->assertMatchesRegularExpression('/Failed to locate civicrm.settings.php/', $output);
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

  protected function proc($commandline, $cwd = NULL, ?array $env = NULL, $input = NULL, $timeout = self::TIMEOUT, array $options = array()) {
    if (!empty($options)) {
      throw new \LogicException("The old options are not supported");
    }
    $p = \Symfony\Component\Process\Process::fromShellCommandline($commandline, $cwd, $env, $input, $timeout);
    return $p;
  }

}
