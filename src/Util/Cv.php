<?php

namespace Civi\Cv\Util;

use Symfony\Component\Process\PhpExecutableFinder;

class Cv {

  /**
   * Call the "cv" command.
   *
   * The following are all roughly equivalent:
   *  - Bash: cv api system.flush
   *  - PHP: $result = json_decode(`cv api system.flush`, TRUE);
   *  - PHP: $result = cv('api system.flush')
   *
   * The `cv()` wrapper is useful because it:
   *  - Decodes output. If the command returns JSON, it's parsed.
   *  - Checks for fatal errors; if encountered, they're rethrown as exceptions.
   *
   * The wrapper should be simple to port to other languages.
   *
   * @param string $cmd
   *   The rest of the command to send.
   * @param string $decode
   *   Ex: 'json', 'phpcode', or 'raw'.
   * @return string
   *   Response output (if the command executed normally).
   * @throws \RuntimeException
   *   If the command terminates abnormally.
   */
  public static function run($cmd, $decode = 'json') {
    $cmd = 'cv ' . $cmd;
    $descriptorSpec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => STDERR);
    $oldOutput = getenv('CV_OUTPUT');
    putenv("CV_OUTPUT=json");
    $process = proc_open($cmd, $descriptorSpec, $pipes);
    putenv("CV_OUTPUT=$oldOutput");
    fclose($pipes[0]);
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
      throw new \RuntimeException("Command failed ($cmd):\n$result");
    }
    switch ($decode) {
      case 'raw':
        return $result;

      case 'phpcode':
        // If the last output is /*PHPCODE*/, then we managed to complete execution.
        if (substr(trim($result), 0, 12) !== "/*BEGINPHP*/" || substr(trim($result), -10) !== "/*ENDPHP*/") {
          throw new \RuntimeException("Command failed ($cmd):\n$result");
        }
        return $result;

      case 'json':
        return json_decode($result, 1);

      default:
        throw new \RuntimeException("Bad decoder format ($decode)");
    }
  }

  /**
   * Call the "cv" command on an interactive "passthru" basis, meaning that output is displayed on our console.
   *
   * @param string $cmd
   *   The rest of the command to send.
   * @return int
   *   The exit code
   * @throws \RuntimeException
   *   If the command terminates abnormally.
   */
  public static function passthru($cmd) {
    $output = \Civi\Cv\Cv::output();
    $fullCmd = static::getCvCommand() . ' ' . $cmd;
    if ($output->isDebug()) {
      $output->writeln("<info>Run subcommand</info> (<comment>$fullCmd</comment>)");
      $output->writeln('');
    }
    $process = proc_open(
      $fullCmd,
      array(
        // 0 => array('pipe', 'r'),
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
      ),
      $pipes
    );
    return proc_close($process);
  }

  public static function getCvCommand(): string {
    $executableFinder = new PhpExecutableFinder();
    $php = $executableFinder->find(FALSE);
    if (!$php) {
      throw new \RuntimeException("Unable to find the PHP executable");
    }
    $parts = $executableFinder->findArguments();
    if (preg_match(';^phar://(.*)/bin/cv$;', CV_BIN, $matches)) {
      $parts[] = $matches[1];
    }
    else {
      $parts[] = CV_BIN;
    }
    $parts = array_merge($parts, static::getPassthruOptions());
    return $php . ' ' . implode(' ', array_map([Process::class, 'lazyEscape'], $parts));
  }

  protected static function getPassthruOptions(): array {
    $input = \Civi\Cv\Cv::input();
    $output = \Civi\Cv\Cv::output();
    $parts = [];
    foreach (['level', 'url', 'user'] as $option) {
      if ($input->getOption($option)) {
        $parts[] = '--' . $option . '=' . $input->getOption($option);
      }
    }
    foreach (['test'] as $option) {
      if ($input->getOption($option)) {
        $parts[] = '--' . $option;
      }
    }
    if ($output->isDebug()) {
      $parts[] = '-vvv';
    }
    elseif ($output->isVeryVerbose()) {
      $parts[] = '-vv';
    }
    elseif ($output->isVerbose()) {
      $parts[] = '-v';
    }
    if ($output->isQuiet()) {
      $parts[] = '--quiet';
    }
    $parts[] = $output->isDecorated() ? '--ansi' : '--no-ansi';
    if (!$input->isInteractive()) {
      $parts[] = '--no-interaction';
    }
    return $parts;
  }

}
