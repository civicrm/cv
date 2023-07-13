<?php

namespace Civi\Cv\Log;

/**
 * @internal
 */
class StdioLogger extends InternalLogger {

  protected $verbose;

  public function __construct(string $topic, bool $verbose = FALSE) {
    parent::__construct($topic);
    $this->verbose = $verbose;
  }

  public function log($level, $message, array $context = []) {
    if ($this->isAnomolous($level) || $this->verbose) {
      $template = "[%s:%s] %s\n";
      fprintf(STDERR, $template, $this->topic, $level, $this->interpolate($message, $context));
    }
  }

}
