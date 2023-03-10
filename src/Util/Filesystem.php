<?php
namespace Civi\Cv\Util;

class Filesystem extends \Symfony\Component\Filesystem\Filesystem {

  /**
   * @param string $path
   * @return string updated $path
   */
  public function toAbsolutePath($path) {
    if (empty($path)) {
      $res = getcwd();
    }
    elseif ($this->isAbsolutePath($path)) {
      $res = $path;
    }
    else {
      $res = getcwd() . DIRECTORY_SEPARATOR . $path;
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
      throw \RuntimeException('Bad rewind');
    }
    if (!ftruncate($fh, 0)) {
      throw \RuntimeException('Bad truncate');
    }

    if (!fwrite($fh, $rawOut)) {
      throw \RuntimeException('Bad write');
    };
    flock($fh, LOCK_UN);

    return fclose($fh);
  }

}
