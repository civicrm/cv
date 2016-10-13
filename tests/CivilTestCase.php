<?php
namespace Civi\Cv;

use Civi\Cv\Util\Process;
use Civi\Cv\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class CivilTestCase extends \PHPUnit_Framework_TestCase {

  /**
   * @var string
   */
  private $originalCwd;

  /**
   * Path to the "cv" binary.
   *
   * @var string
   */
  protected $cv;

  public function setup() {
    $this->originalCwd = getcwd();
    chdir($this->getExampleDir());
    $this->cv = dirname(__DIR__) . '/bin/cv';
  }

  public function tearDown() {
    chdir($this->originalCwd);
  }

  public function getExampleDir() {
    $dir = getenv('CV_TEST_BUILD');
    if (empty($dir)) {
      throw new \RuntimeException('Environment variable CV_TEST_BUILD must point to a civicrm-cms build');
    }
    return $dir;
  }

  /**
   * @param string $command
   */
  protected function cv($command) {
    $process = new \Symfony\Component\Process\Process("{$this->cv} $command");
    return $process;
  }

  protected function cvApi($entity, $action, $params = array()) {
    $input = escapeshellarg(json_encode($params));
    $p = Process::runOk(new \Symfony\Component\Process\Process("echo $input | {$this->cv} api $entity.$action --in=json"));
    $data = json_decode($p->getOutput(), 1);
    return $data;
  }

  /**
   * Create a helper for executing command-tests in our application.
   *
   * @param array $args must include key "command"
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  public function createCommandTester($args) {
    if (!isset($args['command'])) {
      throw new \RuntimeException("Missing mandatory argument: command");
    }
    $application = new Application();
    $command = $application->find($args['command']);
    $commandTester = new CommandTester($command);
    $commandTester->execute($args);
    return $commandTester;
  }

}
