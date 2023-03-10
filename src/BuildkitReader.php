<?php
namespace Civi\Cv;

use Civi\Cv\Util\Filesystem;

class BuildkitReader {

  /**
   * Given a civicrm.settings.php file, find the matching buildkit .sh config file.
   *
   * @param string $settingsFile
   * @return null|string
   */
  public static function findShFile($settingsFile) {
    $fs = new Filesystem();
    $settingsFile = $fs->toAbsolutePath($settingsFile);
    $parts = explode('/', str_replace('\\', '/', $settingsFile));
    while (!empty($parts)) {
      $last = array_pop($parts);
      $basePath = implode('/', $parts);
      $shFile = "$basePath/$last.sh";
      if (is_dir("$basePath/$last") && file_exists($shFile)) {
        // Does it look vaguely like a buildkit config file?
        if (preg_match('/ADMIN_USER=/', file_get_contents($shFile))) {
          return $shFile;
        }
      }
    }
    return NULL;
  }

  /**
   * Parse the key-value paris in buildkit .sh config file.
   *
   * @param string $shFile
   * @return array
   */
  public static function readShFile($shFile) {
    $lines = explode("\n", file_get_contents($shFile));
    $result = array();
    foreach ($lines as $line) {
      if (empty($line) || $line[0] == '#') {
        continue;
      }
      if (preg_match('/^([A-Z0-9_]+)=\"(.*)\"$/', $line, $matches)) {
        $result[$matches[1]] = stripcslashes($matches[2]);
      }
      else {
        throw new \RuntimeException("Malformed line [$line]");
      }
    }
    return $result;
  }

}
