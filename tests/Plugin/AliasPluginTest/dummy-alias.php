<?php

/**
 * This plugin adds support for a site-alias named `@dummy`. If given, then all commands
 * will are routed through a dummy transport -- which simply prints the details about the
 * attempted command.
 */

// Plugin lives in a unique namespace
namespace Civi\Cv\DummyAliasPlugin;

use Civi\Cv\Cv;
use Civi\Cv\CvEvent;
use CvDeps\Symfony\Component\Console\Output\OutputInterface;

if (empty($CV_PLUGIN['protocol']) || $CV_PLUGIN['protocol'] > 1) {
  die("Expect CV_PLUGIN API v1");
}

Cv::dispatcher()->addListener('*.app.site-alias', function(CvEvent $event) {
  if ($event['alias'] === 'dummy') {

    foreach (['app', 'output', 'input'] as $key) {
      if (empty($event[$key])) {
        throw new \RuntimeException("Event *.app.site-alias is missing value for \"$key\"");
      }
    }

    /**
     * @var \Civi\Cv\Util\CvArgvInput $input
     */
    $input = $event['input'];

    /**
     * @var \CvDeps\Symfony\Component\Console\Output\OutputInterface $output
     */
    $output = $event['output'];

    $args = array_map(__NAMESPACE__ . '\\escapeString', $input->getOriginalArgv());
    $fullCommand = implode(' ', $args);

    $event['transport'] = function() use ($input, $output, $fullCommand) {
      $output->writeln("DUMMY: $fullCommand", OutputInterface::OUTPUT_RAW);
    };
  }
});

function escapeString(string $expr): string {
  return preg_match('{^[\w=-]+$}', $expr) ? $expr : escapeshellarg($expr);
}
