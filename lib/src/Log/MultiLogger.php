<?php

namespace Civi\Cv\Log;

/**
 * Forward logs to multiple loggers.
 */
class MultiLogger extends InternalLogger {

  /**
   * @var \Civi\Cv\Log\InternalLogger[]|\Psr\Log\LoggerInterface[]
   */
  protected $loggers = [];

  /**
   * @param string $topic
   * @param \Civi\Cv\Log\InternalLogger[]|\Psr\Log\LoggerInterface[] $loggers
   */
  public function __construct(string $topic, array $loggers = []) {
    parent::__construct($topic);
    $this->loggers = $loggers;
  }

  /**
   * @param string $id
   * @return \Civi\Cv\Log\InternalLogger|\Psr\Log\LoggerInterface|null
   */
  public function getLogger(string $id) {
    return $this->loggers[$id] ?? NULL;
  }

  public function log($level, $message, array $context = []) {
    $errors = [];
    foreach ($this->loggers as $logger) {
      try {
        $logger->log($level, $message, $context);
      }
      catch (\Throwable $t) {
        $errors[] = $t;
      }
    }

    // We'll try to let -some- logger get the message out before we abort.
    if (!empty($errors)) {
      throw new MultiLoggerException(implode("\n", $errors), $level);
    }
  }

}
