<?php
namespace Civi\Cv\Util;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConsoleQueueRunner
 * @package Civi\Cv\Util
 *
 * Execute tasks in a CRM_Queue_Queue, with output directed to the console.
 */
class ConsoleQueueRunner {

  /**
   * @var bool
   */
  private $dryRun;

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  private $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private $output;

  /**
   * @var \CRM_Queue_Queue
   */
  private $queue;

  /**
   * ConsoleQueueRunner constructor.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \CRM_Queue_Queue $queue
   * @param bool $dryRun
   */
  public function __construct(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output, \CRM_Queue_Queue $queue, $dryRun = FALSE) {
    $this->input = $input;
    $this->output = $output;
    $this->queue = $queue;
    $this->dryRun = $dryRun;
  }

  /**
   * @throws \Exception
   */
  public function runAll() {
    $taskCtx = new \CRM_Queue_TaskContext();
    $taskCtx->queue = $this->queue;
    // WISHLIST: Wrap $output
    $taskCtx->log = \Log::singleton('display');
    // CRM_Core_Error::createDebugLogger()

    while ($this->queue->numberOfItems()) {
      // In case we're retrying a failed job.
      $item = $this->queue->stealItem();
      $task = $item->data;

      if ($this->output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
        // Symfony progress bar would be prettier, but they don't allow
        // resetting when the queue-length expands dynamically.
        $this->output->write(".");
      }
      elseif ($this->output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
        $this->output->writeln(sprintf("<info>%s</info>", $task->title));
      }
      elseif ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
        $this->output->writeln(sprintf("<info>%s</info> (<comment>%s</comment>)", $task->title, self::formatTaskCallback($task)));
      }

      if (!$this->dryRun) {
        try {
          $task->run($taskCtx);
        }
        catch (\Exception $e) {
          // WISHLIST: For interactive mode, perhaps allow retry/skip?
          $this->output->writeln("<error>Error executing task: %s</error>", $task->title);
          throw $e;
        }
      }

      $this->queue->deleteItem($item);
    }

    if ($this->output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
      $this->output->writeln("");
    }
  }

  protected static function formatTaskCallback(\CRM_Queue_Task $task) {
    return sprintf("%s(%s)",
      implode('::', (array) $task->callback),
      implode(',', $task->arguments)
    );
  }

}
