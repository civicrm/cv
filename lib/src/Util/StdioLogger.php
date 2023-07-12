<?php

namespace Civi\Cv\Util;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * @internal
 */
class StdioLogger extends AbstractLogger {

  private $errorLevels = [LogLevel::ERROR, LogLevel::EMERGENCY, LogLevel::CRITICAL];

  protected $topic = '';

  protected $verbose;

  public function __construct(string $topic = '', bool $verbose = FALSE) {
    $this->topic = $topic;
    $this->verbose = $verbose;
  }

  public function log($level, $message, array $context = []) {
    $template = "[%s:%s] %s\n";

    if (in_array($level, $this->errorLevels)) {
      fprintf(STDERR, $template, $this->topic, $level, $this->interpolate($message, $context));
    }
    elseif ($level === LogLevel::WARNING || $this->verbose) {
      fprintf(STDOUT, $template, $this->topic, $level, $this->interpolate($message, $context));
    }
  }

  private function interpolate(string $message, array $context): string {
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
    }

    return strtr($message, $replacements);
  }

}
