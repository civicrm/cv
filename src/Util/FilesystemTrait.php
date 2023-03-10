<?php
namespace Civi\Cv\Util;

/**
 * A few selected excerpts from "symfony/filesystem".
 *
 * @see \Symfony\Component\Filesystem\Filesystem
 * @author Fabien Potencier <fabien@symfony.com>
 * @license MIT
 */
trait FilesystemTrait {

  /**
   * @var string|null
   */
  private static $lastError;

  /**
   * Returns whether the file path is an absolute path.
   *
   * @param string $file A file path
   * @return bool
   */
  public function isAbsolutePath($file) {
    return '' !== (string) $file && (strspn($file, '/\\', 0, 1)
        || (\strlen($file) > 3 && ctype_alpha($file[0])
          && ':' === $file[1]
          && strspn($file, '/\\', 2, 1)
        )
        || NULL !== parse_url($file, \PHP_URL_SCHEME)
      );
  }

  /**
   * Removes files or directories.
   *
   * @param string|iterable $files A filename, an array of files, or a \Traversable instance to remove
   *
   * @throws \RuntimeException When removal fails
   */
  public function remove($files) {
    if ($files instanceof \Traversable) {
      $files = iterator_to_array($files, FALSE);
    }
    elseif (!\is_array($files)) {
      $files = [$files];
    }
    $files = array_reverse($files);
    foreach ($files as $file) {
      if (is_link($file)) {
        // See https://bugs.php.net/52176
        if (!(self::box('unlink', $file) || '\\' !== \DIRECTORY_SEPARATOR || self::box('rmdir', $file)) && file_exists($file)) {
          throw new \RuntimeException(sprintf('Failed to remove symlink "%s": ', $file) . self::$lastError);
        }
      }
      elseif (is_dir($file)) {
        $this->remove(new \FilesystemIterator($file, \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS));

        if (!self::box('rmdir', $file) && file_exists($file)) {
          throw new \RuntimeException(sprintf('Failed to remove directory "%s": ', $file) . self::$lastError);
        }
      }
      elseif (!self::box('unlink', $file) && (str_contains(self::$lastError, 'Permission denied') || file_exists($file))) {
        throw new \RuntimeException(sprintf('Failed to remove file "%s": ', $file) . self::$lastError);
      }
    }
  }

  /**
   * @param callable $func
   *   File I/O function from PHP stdlib
   * @param mixed ...$args
   * @return mixed
   */
  private static function box(callable $func, ...$args) {
    self::$lastError = NULL;
    set_error_handler(__CLASS__ . '::handleError');
    try {
      $result = $func(...$args);
      restore_error_handler();

      return $result;
    }
    catch (\Throwable $e) {
    }
    restore_error_handler();

    throw $e;
  }

  /**
   * @internal
   */
  public static function handleError(int $type, string $msg) {
    self::$lastError = $msg;
  }

}
