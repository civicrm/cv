<?php
namespace Civi\Cv;

use Civi\Cv\Util\Process;

trait CvTestTrait {

  /**
   * Create a process for `cv` subcommand.
   *
   * @param string $command
   *   Ex: "vars:show --out=serialize".
   * @return \Symfony\Component\Process\Process
   */
  protected function cv($command) {
    $cvPath = $this->getCvPath();
    $process = \Symfony\Component\Process\Process::fromShellCommandline("{$cvPath} $command");
    return $process;
  }

  /**
   * Run a `cv` subcommand. Assert success and return output.
   *
   * @param string $cmd
   * @return string
   */
  protected function cvOk($cmd) {
    $p = Process::runOk($this->cv($cmd));
    return $p->getOutput();
  }

  /**
   * Run a `cv` subcommand. Assert success and return output.
   *
   * @param string $cmd
   * @return string
   */
  protected function cvFail($cmd) {
    $p = Process::runFail($this->cv($cmd));
    return $p->getErrorOutput() . $p->getOutput();
  }

  /**
   * Run a `cv` subcommand. Assert success and return output.
   *
   * @param string $cmd
   * @return string
   */
  protected function cvJsonOk($cmd) {
    $p = Process::runOk($this->cv($cmd));
    return json_decode($p->getOutput(), 1);
  }

  private function getCvPath(): string {
    return getenv('CV_TEST_BINARY') ?: dirname(__DIR__) . '/bin/cv';
  }

  protected function isCvPharTest(): bool {
    return (bool) preg_match(';\.phar$;', $this->getCvPath());
  }

}
