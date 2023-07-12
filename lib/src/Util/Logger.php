<?php

namespace Civi\Cv\Util;

use Psr\Log\LoggerInterface;

class Logger {

  /**
   * Determine which logger to use.
   *
   * @param array $options
   *   Some mix of the following:
   *   - log: \Psr\Log\LoggerInterface (If given, send log messages here)
   *   - output: Symfony OutputInterface. (Fallback for handling logs - in absence of 'log')
   * @param string $topic
   * @return \Psr\Log\LoggerInterface
   */
  public static function resolve(array $options, string $topic): LoggerInterface {
    if (!empty($options['log'])) {
      return $options['log'];
    }
    elseif (!empty($options['output'])) {
      return new SymfonyConsoleLogger($options['output'], $topic);
    }
    else {
      return new StdioLogger($topic);
    }
  }

}
