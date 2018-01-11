<?php
namespace Civi\Cv\Util;
class Process {

  /**
   * Helper which synchronously runs a command and verifies that it doesn't generate an error.
   *
   * @param \Symfony\Component\Process\Process $process
   * @return \Symfony\Component\Process\Process
   * @throws \RuntimeException
   */
  public static function runDebug($process) {
    if (getenv('DEBUG')) {
      var_dump(array(
        'Working Directory' => $process->getWorkingDirectory(),
        'Command' => $process->getCommandLine(),
      ));
      ob_flush();
    }

    $process->run(function ($type, $buffer) {
      if (getenv('DEBUG')) {
        if (\Symfony\Component\Process\Process::ERR === $type) {
          echo 'STDERR > ' . $buffer;
        }
        else {
          echo 'STDOUT > ' . $buffer;
        }
        ob_flush();
      }
    });

    return $process;
  }

  /**
   * Helper which synchronously runs a command and verifies that it doesn't generate an error.
   *
   * @param \Symfony\Component\Process\Process $process
   * @return \Symfony\Component\Process\Process
   * @throws \RuntimeException
   */
  public static function runOk(\Symfony\Component\Process\Process $process) {
    self::runDebug($process);
    if (!$process->isSuccessful()) {
      throw new \Civi\Cv\Exception\ProcessErrorException($process);
    }
    return $process;
  }

  /**
   * Helper which synchronously runs a command and verifies that it generates an error.
   *
   * @param \Symfony\Component\Process\Process $process
   * @return \Symfony\Component\Process\Process
   * @throws \RuntimeException
   */
  public static function runFail(\Symfony\Component\Process\Process $process) {
    self::runDebug($process);
    if ($process->isSuccessful()) {
      Process::dump($process);
      throw new \Civi\Cv\Exception\ProcessErrorException($process, "Process succeeded unexpectedly");
    }
    return $process;
  }

  /**
   * Determine full path to an external command (by searching PATH).
   *
   * @param string $name
   * @return null|string
   */
  public static function findCommand($name) {
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    foreach ($paths as $path) {
      if (file_exists("$path/$name")) {
        return "$path/$name";
      }
    }
    return NULL;
  }

  /**
   * @param \Symfony\Component\Process\Process $process
   */
  public static function dump(\Symfony\Component\Process\Process $process) {
    var_dump(array(
      'Working Directory' => $process->getWorkingDirectory(),
      'Command' => $process->getCommandLine(),
      'Exit Code' => $process->getExitCode(),
      'Output' => $process->getOutput(),
      'Error Output' => $process->getErrorOutput(),
    ));
  }

}
