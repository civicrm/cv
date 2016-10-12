<?php

namespace Civi\Cv\Util;
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

}
