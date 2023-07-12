<?php

namespace Civi\Cv\PharOut;

use TYPO3\PharStreamWrapper\Helper;
use TYPO3\PharStreamWrapper\Exception;

/**
 * Accept PHP from PHAR files if either (a) they have the '.phar' extension or
 * (b) it is the top-level/main file.
 *
 * @see \TYPO3\PharStreamWrapper\Interceptor\PharExtensionInterceptor
 */
trait PharPolicyTrait {

  /**
   * The initial/top-level/main script.
   *
   * @var string|null
   */
  private $mainFile;

  /**
   * Determines whether the base file name has a ".phar" suffix.
   *
   * @param string $path
   * @param string $command
   * @return bool
   * @throws Exception
   */
  public function assert($path, $command): bool {
    if ($this->mainFile === NULL) {
      $this->mainFile = $this->findMainFile();
    }

    $baseFile = Helper::determineBaseFile($path);
    if ($baseFile === NULL) {
      throw new Exception(sprintf('Failed to identify origin of "%s"', $path), 1535198703);
    }

    if (($baseFile === $this->mainFile) || (strtolower(pathinfo($baseFile, PATHINFO_EXTENSION)) === 'phar')) {
      return TRUE;
    }

    throw new Exception(sprintf('File "%s" does not resolve to approved PHAR location.', $path), 1535198703);
  }

  /**
   * Determine the 'main' script that started this process.
   *
   * @return string|null
   */
  private function findMainFile() {
    if (!in_array(PHP_SAPI, ['cli', 'phpdbg'])) {
      return NULL;
    }

    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    do {
      $caller = array_pop($backtrace);
    } while (empty($caller['file']) && !empty($backtrace));
    return isset($caller['file']) ? Helper::determineBaseFile($caller['file']) : NULL;
  }

}
