<?php
namespace Civi\Cv\Util;

use Civi\Cv\Util\Process as ProcessUtil;
use Symfony\Component\Process\Process;

/**
 * @group std
 * @group util
 */
class ProcessTest extends \PHPUnit_Framework_TestCase {

  public function testSprintf() {
    $this->assertEquals(
      'ls \'whiz=1&amp;bang=1\' \'Who\'\\\'\'s on first\'',
      ProcessUtil::sprintf('ls %s %s', 'whiz=1&amp;bang=1', "Who's on first")
    );

    $this->assertEquals(
      'ls \'/home/foo bar\' /foo/bar',
      ProcessUtil::sprintf('ls %s %s', '/home/foo bar', '/foo/bar')
    );

    $this->assertEquals(
      'ls /foo/bar',
      ProcessUtil::sprintf('ls %s', '/foo/bar')
    );

    $echoResult = ProcessUtil::runOk(new Process(
      ProcessUtil::sprintf('echo %s', 'whiz=1&amp;bang=>')
    ));
    $this->assertEquals('whiz=1&amp;bang=>', rtrim($echoResult->getOutput()));
  }

  public function testRunOk_pass() {
    $process = ProcessUtil::runOk(new \Symfony\Component\Process\Process("echo times were good"));
    $this->assertEquals("times were good", trim($process->getOutput()));
  }

  public function testRunOk_fail() {
    try {
      ProcessUtil::runOk(new \Symfony\Component\Process\Process("echo tragedy befell the software > /dev/stderr; exit 1"));
      $this->fail("Failed to generate expected exception");
    }
    catch (\Civi\Cv\Exception\ProcessErrorException $e) {
      $this->assertEquals("tragedy befell the software", trim($e->getProcess()->getErrorOutput()));
    }
  }

}
