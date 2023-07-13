<?php

namespace Civi\Cv\Log;

class Logger {

  /**
   * Determine which logger to use.
   *
   * @param array $options
   *   Some mix of the following:
   *   - log: \Psr\Log\LoggerInterface|\Civi\Cv\Log\InternalLogger (If given, send log messages here)
   *   - output: Symfony OutputInterface. (Fallback for handling logs - in absence of 'log')
   * @param string $topic
   * @return \Psr\Log\LoggerInterface|\Civi\Cv\Log\InternalLogger
   */
  public static function resolve(array $options, string $topic) {
    if (!empty($options['log'])) {
      return $options['log'];
    }
    elseif (!empty($options['output'])) {
      return new SymfonyConsoleLogger($topic, $options['output']);
    }
    else {
      return new StderrLogger($topic);
    }
  }

}
