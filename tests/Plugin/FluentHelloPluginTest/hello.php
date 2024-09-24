<?php

use Civi\Cv\Cv;
use Civi\Cv\Command\CvCommand;
use CvDeps\Symfony\Component\Console\Input\InputInterface;
use CvDeps\Symfony\Component\Console\Output\OutputInterface;

if (empty($CV_PLUGIN['protocol']) || $CV_PLUGIN['protocol'] > 1) {
  die("Expect CV_PLUGIN API v1");
}

if (!preg_match(';^[\w_-]+$;', $CV_PLUGIN['appName'])) {
  throw new \RuntimeException("Invalid CV_PLUGIN[appName]" . json_encode($CV_PLUGIN['appName']));
}

if (!preg_match(';^([0-9x\.]+(-[\w-]+)?|UNKNOWN)$;', $CV_PLUGIN['appVersion'])) {
  throw new \RuntimeException("Invalid CV_PLUGIN[appVersion]: " . json_encode($CV_PLUGIN['appVersion']));
}

if ($CV_PLUGIN['name'] !== 'hello') {
  throw new \RuntimeException("Invalid CV_PLUGIN[name]");
}
if (realpath($CV_PLUGIN['file']) !== realpath(__FILE__)) {
  throw new \RuntimeException("Invalid CV_PLUGIN[file]");
}

Cv::dispatcher()->addListener('*.app.boot', function ($e) {
  Cv::io()->writeln("Hey-yo during initial bootstrap!");
});

Cv::dispatcher()->addListener('cv.app.commands', function ($e) {

  $e['commands'][] = (new CvCommand('hello:normal'))
    ->setDescription('Say a greeting')
    ->addArgument('name')
    ->setCode(function($input, $output) {
      // ASSERT: With setCode(), it's OK to use un-hinted inputs.
      if ($input->getArgument('name') !== Cv::input()->getArgument('name')) {
        throw new \RuntimeException("Argument \"name\" is inconsistent!");
      }
      if (!Civi\Core\Container::isContainerBooted()) {
        throw new \LogicException("Container should have been booted by CvCommand!");
      }
      $name = $input->getArgument('name') ?: 'world';
      $output->writeln("Hey-yo $name via parameter!");
      Cv::io()->writeln("Hey-yo $name via StyleInterface!");
      return 0;
    });

  $e['commands'][] = (new CvCommand('hello:noboot'))
    ->setDescription('Say a greeting')
    ->addArgument('name')
    ->setBootOptions(['auto' => FALSE])
    ->setCode(function(InputInterface $input, OutputInterface $output) {
      // ASSERT: With setCode(), it's OK to use hinted inputs.
      if ($input->getArgument('name') !== Cv::input()->getArgument('name')) {
        throw new \RuntimeException("Argument \"name\" is inconsistent!");
      }
      if (class_exists('Civi\Core\Container')) {
        throw new \LogicException("Container should not have been booted by CvCommand!");
      }
      $name = $input->getArgument('name') ?: 'world';
      $output->writeln("Hey-yo $name via parameter!");
      Cv::io()->writeln("Hey-yo $name via StyleInterface!");
      return 0;
    });

});
