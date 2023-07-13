<?php

namespace Civi\Cv\Log;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Send log messages to the console with Symfony formatting codes.
 */
class SymfonyConsoleLogger extends InternalLogger {

  private $verbosityLevelMap;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  /**
   * @param string $topic
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function __construct(string $topic, $output) {
    parent::__construct($topic);
    $this->output = $output;
    $this->verbosityLevelMap = [
      'emergency' => OutputInterface::VERBOSITY_NORMAL,
      'alert' => OutputInterface::VERBOSITY_NORMAL,
      'critical' => OutputInterface::VERBOSITY_NORMAL,
      'error' => OutputInterface::VERBOSITY_NORMAL,
      'warning' => OutputInterface::VERBOSITY_NORMAL,
      'notice' => OutputInterface::VERBOSITY_VERBOSE,
      'info' => OutputInterface::VERBOSITY_VERY_VERBOSE,
      'debug' => OutputInterface::VERBOSITY_DEBUG,
    ];
  }

  public function log($level, $message, array $context = []) {
    $output = $this->output;

    if ($output instanceof ConsoleOutputInterface) {
      $output = $output->getErrorOutput();
    }

    if ($this->isAnomolous($level)) {
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

  protected function decorateInterpolatedValue(string $value): string {
    return "<comment>$value</comment>";
  }

}
