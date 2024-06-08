<?php

use Civi\Cv\Cv;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (empty($CV_PLUGIN['protocol']) || $CV_PLUGIN['protocol'] > 1) {
  die("Expect CV_PLUGIN API v1");
}

Cv::dispatcher()->addListener('cv.app.commands', function ($e) {
  $e['commands'][] = new class extends \Symfony\Component\Console\Command\Command {

    protected function configure() {
      $this->setName('hello')->setDescription('Say a greeting');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
      $output->writeln('Hello there!');
    }

  };
});
