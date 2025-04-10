<?php

namespace Civi\Cv\Command;

use Civi;
use CRM_Queue_Runner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class QueueNextCommand extends CvCommand {

  protected function configure() {
    $this
      ->setName('console-queue:run-next')
      ->setDescription('(INTERNAL) Run the next task in queue')
      ->setHidden(TRUE)
      ->addOption('steal', NULL, InputOption::VALUE_NONE)
      ->addOption('skip', NULL, InputOption::VALUE_NONE)
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Store outcome in a JSON file')
      ->addOption('queue', NULL, InputOption::VALUE_REQUIRED, 'Queue name')
      ->addOption('queue-spec', NULL, InputOption::VALUE_REQUIRED, 'Queue specification (Base64-JSON)');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $outFile = $input->getOption('out');
    if ($outFile && file_exists($outFile)) {
      $this->writeFile($outFile, '');
    }

    $queue = $this->getQueue($input);

    $runner = new CRM_Queue_Runner([
      'queue' => $queue,
    ]);
    if ($input->getOption('skip')) {
      $result = $runner->skipNext($input->getOption('steal'));
    }
    else {
      $result = $runner->runNext($input->getOption('steal'));
    }

    if ($outFile) {
      $this->writeFile($outFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    if (!empty($result['exception'])) {
      throw $result['exception'];
    }
    return empty($result['is_error']) ? 0 : 1;
  }

  private function writeFile(string $outFile, string $data): void {
    $parent = dirname($outFile);
    if (!is_dir($parent)) {
      if (!mkdir($parent, 0777, TRUE)) {
        throw new \RuntimeException("Failed to create directory $parent");
      }
    }
    $result = file_put_contents($outFile, $data);
    if ($result === FALSE) {
      throw new \RuntimeException("Failed to write to $outFile");
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return \CRM_Queue_Queue
   */
  private function getQueue(InputInterface $input): \CRM_Queue_Queue {
    // cv is evergreen and may be used on old versions, so we accept either --queue-spec (old style, non-persistent queues)
    // or --queue (new style, persistent queues).
    if ($input->getOption('queue-spec')) {
      // For old-fashioned systems which lack support for persistent queue-definitions.
      $spec = json_decode(base64_decode($input->getOption('queue-spec')), TRUE);
      if (empty($spec)) {
        throw new \InvalidArgumentException('Queue spec is empty or malformed');
      }
      $queue = \CRM_Queue_Service::singleton()->create($spec);
    }
    elseif ($input->getOption('queue')) {
      $queue = Civi::queue($input->getOption('queue'));
    }
    else {
      throw new \LogicException("Must specify either --queue or --queue-spec");
    }
    return $queue;
  }

}
