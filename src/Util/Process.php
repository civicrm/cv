<?php
namespace Civi\Cv\Util;

class Process {

  /**
   * Evaluate a string, replacing `%s` tokens with escaped strings.
   *
   * Ex: sprintf('ls -lr %s', $theDir);
   *
   * @param string $expr
   * @return mixed
   * @see escapeshellarg()
   */
  public static function sprintf($expr) {
    $args = func_get_args();
    $newArgs = array();
    $newArgs[] = array_shift($args);
    foreach ($args as $arg) {
      $newArgs[] = static::lazyEscape($arg);
    }
    return call_user_func_array('sprintf', $newArgs);
  }

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
   * Determine if $file is a shell script.
   *
   * @param string $file
   * @return bool
   */
  public static function isShellScript($file) {
    $firstLine = file_get_contents($file, FALSE, NULL, 0, 120);
    list($firstLine) = explode("\n", $firstLine);
    return (bool) preg_match(';^#.*bin.*sh;', $firstLine);
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

  /**
   * Escape a value for use as a shell argument.
   *
   * This is basically the same as `escapeshellarg()`, but quotation marks can be skipped for
   * some simple strings.
   *
   * @param string $value
   * @return string
   */
  public static function lazyEscape(string $value): string {
    if (preg_match('/^[a-zA-Z0-9_\.\-\/=]*$/', $value)) {
      return $value;
    }
    else {
      return escapeshellarg($value);
    }
  }

}
