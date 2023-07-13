<?php

namespace Civi\Cv\Util;

use Civi\Cv\Log\InternalLogger;
use Psr\Log\AbstractLogger;

/**
 * Convert from an InternalLogger to an PSR-3 LoggerInterface.
 */
class PsrLogger extends AbstractLogger {

  protected $internalLog;

  public function __construct(InternalLogger $internalLog) {
    $this->internalLog = $internalLog;
  }

  public function log($level, $message, array $context = array()): void {
    $this->internalLog->log($level, $message, $context);
  }

}
