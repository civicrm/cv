<?php

namespace Civi\Cv\Util;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class SymfonyConsoleLogger extends AbstractLogger {

  private $verbosityLevelMap = [
    LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
    LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
    LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
    LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
    LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
    LogLevel::NOTICE => OutputInterface::VERBOSITY_VERBOSE,
    LogLevel::INFO => OutputInterface::VERBOSITY_VERY_VERBOSE,
    LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
  ];

  private $errorLevels = [LogLevel::ERROR, LogLevel::EMERGENCY, LogLevel::CRITICAL];

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  protected $topic = '';

  public function __construct(OutputInterface $output, string $topic = '') {
    $this->output = $output;
    $this->topic = $topic;
  }

  public function log($level, $message, array $context = []) {
    $output = $this->output;

    if (in_array($level, $this->errorLevels)) {
      if ($output instanceof ConsoleOutputInterface) {
        $output = $output->getErrorOutput();
      }
      $template = '<error>[%s:%s]</error> %s';
    }
    else {
      $template = '<info>[%s:%s]</info> %s';
    }

    if ($output->getVerbosity() >= $this->verbosityLevelMap[$level]) {
      $output->writeln(sprintf($template, $this->topic, $level, $this->interpolate($message, $context)),
        $this->verbosityLevelMap[$level]);
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
      $replacements["{{$key}}"] = '<comment>' . $replacements["{{$key}}"] . '</comment>';
    }

    return strtr($message, $replacements);
  }

}
