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
   * @var \Symfony\Component\Console\Style\SymfonyStyle
   */
  private $io;

  /**
   * @var \CRM_Queue_Queue
   */
  private $queue;

  /**
   * @var bool
   */
  private $step;

  /**
   * ConsoleQueueRunner constructor.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   * @param \CRM_Queue_Queue $queue
   * @param bool $dryRun
   * @param bool $step
   */
  public function __construct(\Symfony\Component\Console\Style\SymfonyStyle $io, \CRM_Queue_Queue $queue, $dryRun = FALSE, $step = FALSE) {
    $this->io = $io;
    $this->queue = $queue;
    $this->dryRun = $dryRun;
    $this->step = (bool) $step;
  }

  /**
   * @throws \Exception
   */
  public function runAll() {
    /** @var \Symfony\Component\Console\Style\SymfonyStyle $io */
    $io = $this->io;

    $taskCtx = new \CRM_Queue_TaskContext();
    $taskCtx->queue = $this->queue;
    // WISHLIST: Wrap $output
    $taskCtx->log = \Log::singleton('display');
    // CRM_Core_Error::createDebugLogger()

    while ($this->queue->numberOfItems()) {
      // In case we're retrying a failed job.
      $item = $this->queue->stealItem();
      $task = $item->data;

      if ($io->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
        // Symfony progress bar would be prettier, but (when last checked) they didn't allow
        // resetting when the queue-length expands dynamically.
        $io->write(".");
      }
      elseif ($io->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
        $io->writeln(sprintf("<info>%s</info>", $task->title));
      }
      elseif ($io->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
        $io->writeln(sprintf("<info>%s</info> (<comment>%s</comment>)", $task->title, self::formatTaskCallback($task)));
      }

      $action = 'y';
      if ($this->step) {
        $action = $io->choice('Execute this step?', ['y' => 'yes', 's' => 'skip', 'a' => 'abort'], 'y');
      }
      if ($action === 'a') {
        throw new \Exception('Aborted');
      }

      if ($action === 'y' && !$this->dryRun) {
        try {
          $isOK = $task->run($taskCtx);
          if (!$isOK) {
            throw new \Exception('Task returned false');
          }
        }
        catch (\Exception $e) {
          // WISHLIST: For interactive mode, perhaps allow retry/skip?
          $io->writeln(sprintf("<error>Error executing task \"%s\"</error>", $task->title));
          throw $e;
        }
      }

      $this->queue->deleteItem($item);
    }

    if ($io->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
      $io->newLine();
    }
  }

  protected static function formatTaskCallback(\CRM_Queue_Task $task) {
    return sprintf("%s(%s)",
      implode('::', (array) $task->callback),
      implode(',', $task->arguments)
    );
  }

}
