<?php

namespace Civi\Cv\Log;

/**
 * This is a work-a-like for PSR Log.
 *
 * In the wild, there are too many major versions of `psr/log` floating around,
 * and we have to execute without knowing what's needed.
 *
 * Downstream consumers may know more about which flavor is in play. If they want
 * to convert between contracts, it should be fairly easy:
 *
 *   // Take the $internalLog and present as a $psrLog.
 *   $psrLog = new class extends \Psr\Log\AbstractLogger {
 *      public function log(...) { $internalLog->log(...); }
 *   };
 *
 *   // Take the $psrLog and present as an $internalLog.
 *   $internalLog = new class extends \Civi\Cv\Log\InternalLogger {
 *     public function log(...) { $psrLog->log(...); }
 *   };
 */
abstract class InternalLogger {

  protected $topic;

  public function __construct(string $topic) {
    $this->topic = $topic;
  }

  protected function interpolate(string $message, array $context): string {
    if (strpos($message, '{') === FALSE) {
      return $message;
    }

    $replacements = [];
    foreach ($context as $key => $val) {
      if (NULL === $val || is_scalar($val) || (\is_object($val) && method_exists($val, '__toString'))) {
        $replacements["{{$key}}"] = $val;
      }
      elseif ($val instanceof \DateTimeInterface) {
        $replacements["{{$key}}"] = $val->format(\DateTime::RFC3339);
      }
      elseif (\is_object($val)) {
        $replacements["{{$key}}"] = '[object ' . \get_class($val) . ']';
      }
      else {
        $replacements["{{$key}}"] = '[' . \gettype($val) . ']';
      }
      $replacements["{{$key}}"] = $this->decorateInterpolatedValue($replacements["{{$key}}"]);
    }

    return strtr($message, $replacements);
  }

  protected function decorateInterpolatedValue(string $value): string {
    return $value;
  }

  /**
   * @param $level
   * @return bool
   */
  protected function isAnomolous($level): bool {
    return in_array($level, ['warning', 'error', 'emergency', 'critical']);
  }

  /**
   * Logs with an arbitrary level.
   *
   * @param mixed $level
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   *
   * @throws \Psr\Log\InvalidArgumentException
   */
  abstract public function log($level, $message, array $context = array());

  /**
   * System is unusable.
   *
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   */
  public function emergency($message, array $context = array()) {
    $this->log('emergency', $message, $context);
  }

  /**
   * Action must be taken immediately.
   *
   * Example: Entire website down, database unavailable, etc. This should
   * trigger the SMS alerts and wake you up.
   *
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   */
  public function alert($message, array $context = array()) {
    $this->log('alert', $message, $context);
  }

  /**
   * Critical conditions.
   *
   * Example: Application component unavailable, unexpected exception.
   *
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   */
  public function critical($message, array $context = array()) {
    $this->log('critical', $message, $context);
  }

  /**
   * Runtime errors that do not require immediate action but should typically
   * be logged and monitored.
   *
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   */
  public function error($message, array $context = array()) {
    $this->log('error', $message, $context);
  }

  /**
   * Exceptional occurrences that are not errors.
   *
   * Example: Use of deprecated APIs, poor use of an API, undesirable things
   * that are not necessarily wrong.
   *
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   */
  public function warning($message, array $context = array()) {
    $this->log('warning', $message, $context);
  }

  /**
   * Normal but significant events.
   *
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   */
  public function notice($message, array $context = array()) {
    $this->log('notice', $message, $context);
  }

  /**
   * Interesting events.
   *
   * Example: User logs in, SQL logs.
   *
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   */
  public function info($message, array $context = array()) {
    $this->log('info', $message, $context);
  }

  /**
   * Detailed debug information.
   *
   * @param string $message
   * @param mixed[] $context
   *
   * @return void
   */
  public function debug($message, array $context = array()) {
    $this->log('debug', $message, $context);
  }

}
