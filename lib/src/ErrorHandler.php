<?php
namespace Civi\Cv;

/**
 * This is all so evil. It shouldn't exist. God save ye wretched souls who
 * seek to reconcile all the error-handlers of all the platforms.
 */
class ErrorHandler {

  private static $hasShutdown = FALSE;
  private static $count = 0;
  private static $renderer = NULL;

  public static function pushHandler() {
    static::$count++;
    if (!static::$hasShutdown) {
      static::$hasShutdown = TRUE;
      register_shutdown_function([static::class, 'onShutdown']);
    }
    // set_error_handler([static::class, 'onError']);
    set_error_handler([static::class, 'onError']);
    set_exception_handler([static::class, 'onException']);

  }

  public static function popHandler() {
    static::$count--;
    restore_error_handler();
    restore_exception_handler();
  }

  public static function onShutdown() {
    if (static::$count > 0) {
      $error = error_get_last();
      if (isset($error['type']) && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR))) {
        // Something - like a bad eval() - interrupted normal execution.
        // Make sure the status code reflects that.
        exit(255);
      }
    }
  }

  public static function onError($errorLevel, $message, $filename, $line) {
    if ($errorLevel & error_reporting()) {
      $errorType = static::getErrorTypes()[$errorLevel] ?: "Unknown[$errorLevel]";
      fprintf(STDERR, "[%s] %s at %s:%d\n", $errorType, $message, $filename, $line);
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @param \Throwable $exception
   */
  public static function onException($exception) {
    if (isset(static::$renderer)) {
      call_user_func(static::$renderer, $exception);
    }
    else {
      fprintf(STDERR, "Exception: %s (%s)\n%s", $exception->getMessage(), get_class($exception), $exception->getTraceAsString());
    }
    exit(255);
  }

  /**
   * @param callable|null $renderer
   */
  public static function setRenderer(?callable $renderer): void {
    self::$renderer = $renderer;
  }

  protected static function getErrorTypes(): array {
    $const = [
      E_ERROR => 'PHP Error',
      E_WARNING => 'PHP Warning',
      E_PARSE => 'PHP Parse Error',
      E_NOTICE => 'PHP Notice',
      E_CORE_ERROR => 'PHP Core Error',
      E_CORE_WARNING => 'PHP Core Warning',
      E_COMPILE_ERROR => 'PHP Compile Error',
      E_COMPILE_WARNING => 'PHP Compile Warning',
      E_USER_ERROR => 'PHP User Error',
      E_USER_WARNING => 'PHP User Warning',
      E_USER_NOTICE => 'PHP User Notice',
      E_RECOVERABLE_ERROR => 'PHP Recoverable Fatal Error',
      E_DEPRECATED => 'PHP Deprecation',
      E_USER_DEPRECATED => 'PHP User Deprecation',
    ];
    if (version_compare(phpversion(), '8.4', '<')) {
      $const[constant('E_STRICT')] = 'PHP Strict Warning';
      // https://wiki.php.net/rfc/deprecations_php_8_4#remove_e_strict_error_level_and_deprecate_e_strict_constant
      // In theory, once cv shifts to 8.x only, we can simplify this.
    }
    return $const;
  }

}
