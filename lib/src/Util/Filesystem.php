<?php
namespace Civi\Cv\Util;

class Filesystem {

  use FilesystemTrait;

  public static function exists(?string $path): bool {
    return $path !== NULL && file_exists($path);
  }

  /**
   * Determine whether the given $path can be created.
   *
   * It does not matter if the parent exists already (if the parent is creatable).
   *
   * It only matters if we have sufficient write access to some ancestor.
   *
   * @param string $path
   *   The file that you would like to create.
   * @return bool
   */
  public static function isCreatable(string $path): bool {
    if (file_exists($path)) {
      return is_writable($path);
    }

    $iter = $path;
    while (!empty($iter) && dirname($iter) !== $iter) {
      if (file_exists($iter)) {
        return is_dir($iter) && is_writable($iter);
      }
      $iter = dirname($iter);
    }
    return FALSE;
  }

  /**
   * @return false|string
   */
  public function pwd() {
    // exec(pwd) works better with symlinked source trees, but it's
    // probably not portable to Windows.
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      return getcwd();
    }
    else {
      exec('pwd', $output);
      return trim(implode("\n", $output));
    }
  }

  /**
   * @param string $path
   * @return string updated $path
   */
  public function toAbsolutePath($path) {
    if (empty($path)) {
      $res = $this->pwd();
    }
    elseif ($this->isAbsolutePath($path)) {
      $res = $path;
    }
    else {
      $res = $this->pwd() . DIRECTORY_SEPARATOR . $path;
    }
    if (is_dir($res)) {
      return realpath($res);
    }
    else {
      return $res;
    }
  }

  /**
   * @param array $paths of string
   * @return array updated paths
   */
  public function toAbsolutePaths($paths) {
    $result = array();
    foreach ($paths as $path) {
      $result[] = $this->toAbsolutePath($path);
    }
    return $result;
  }

  public function isDescendent($child, $parent) {
    $parent = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $child = rtrim($child, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strlen($parent) >= strlen($child)) {
      return FALSE;
    }
    return ($parent == substr($child, 0, strlen($parent)));
  }

  /**
   * Atomically read, filter, and write a file.
   *
   * @param string $file
   * @param callable $filter
   *   A function which accepts full file content as input,
   *   and returns new content as output.
   * @param int|float $maxWait
   * @return bool
   * @throws \RuntimeException
   */
  public function update($file, $filter, $maxWait = 5.0) {
    $mode = file_exists($file) ? 'r+' : 'w+';
    if (!($fh = fopen($file, $mode))) {
      throw new \RuntimeException("Failed to open");
    }

    $start = microtime(TRUE);
    do {
      $locked = flock($fh, LOCK_EX | LOCK_NB);
      if (!$locked && microtime(TRUE) - $start > $maxWait) {
        throw new \RuntimeException("Failed to lock");
      }
      if (!$locked) {
        usleep(rand(20, 100) * 1000);
      }
    } while (!$locked);

    // TODO throw an error $maxSize exceeded.
    $buf = '';
    while (!feof($fh)) {
      $buf .= fread($fh, 1024 * 1024);
    }
    $rawOut = call_user_func($filter, $buf);

    if (!rewind($fh)) {
      throw new \RuntimeException('Bad rewind');
    }
    if (!ftruncate($fh, 0)) {
      throw new \RuntimeException('Bad truncate');
    }

    if (!fwrite($fh, $rawOut)) {
      throw new \RuntimeException('Bad write');
    };
    flock($fh, LOCK_UN);

    return fclose($fh);
  }

}
