<?php
namespace Civi\Cv\Util;

use Civi\Cv\Exception\QueueTaskException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConsoleSubprocessQueueRunner
 * @package Civi\Cv\Util
 *
 * Execute tasks in a CRM_Queue_Queue, with output directed to the console.
 *
 * Similar to ConsoleQueueRunner, but this executes each task in a new PHP subprocess.
 *
 * @see \Civi\Cv\Util\ConsoleQueueRunner
 */
class ConsoleSubprocessQueueRunner {

  /**
   * @var bool
   */
  private $dryRun;

  /**
   * @var \Symfony\Component\Console\Style\SymfonyStyle
   */
  private $io;

  /**
   * @var array
   */
  private $queueSpec;

  /**
   * @var bool
   */
  private $step;

  /**
   * Temporary file where we can ask the subprocess to store detailed information about its outcome.
   *
   * @var string
   */
  private $stateFile;

  /**
   * ConsoleQueueRunner constructor.
   *
   * @param \Symfony\Component\Console\Style\StyleInterface $io
   * @param array $queueSpec
   * @param bool $dryRun
   * @param bool $step
   */
  public function __construct(\Symfony\Component\Console\Style\StyleInterface $io, array $queueSpec, $dryRun = FALSE, $step = FALSE) {
    $this->io = $io;
    $this->queueSpec = $queueSpec;
    $this->dryRun = $dryRun;
    $this->step = (bool) $step;
    $this->stateFile = $this->pickStateFile();
  }

  public function __destruct() {
    if ($this->stateFile && file_exists($this->stateFile)) {
      @unlink($this->stateFile);
    }
  }

  private function pickStateFile(): string {
    $id = \uniqid();
    if (getenv('XDG_STATE_HOME')) {
      return implode(DIRECTORY_SEPARATOR, [getenv('XDG_STATE_HOME'), 'cv', "dl-{$id}.json"]);
    }
    $home = getenv('HOME') ? getenv('HOME') : getenv('USERPROFILE');
    if (!empty($home) && file_exists($home)) {
      return implode(DIRECTORY_SEPARATOR, [getenv('HOME'), '.cv', 'state', "dl-{$id}.json"]);
    }
    throw new \RuntimeException("Failed to pick state-file. Please set one of: HOME, USERPROFILE, XDG_STATE_HOME");
  }

  private function getState(): ?array {
    if (!file_exists($this->stateFile)) {
      return NULL;
    }
    return json_decode(file_get_contents($this->stateFile), TRUE);
  }

  /**
   * @throws \Exception
   */
  public function runAll() {
    /** @var \Symfony\Component\Console\Style\SymfonyStyle $io */
    $io = $this->io;
    $queue = \CRM_Queue_Service::singleton()->create($this->queueSpec);

    while ($queue->numberOfItems()) {
      // In case we're retrying a failed job.
      $item = $queue->stealItem();
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

      $specArg = escapeshellarg(base64_encode(json_encode($this->queueSpec, JSON_UNESCAPED_SLASHES)));
      $fileArg = escapeshellarg($this->stateFile);
      $runCmd = "console-queue:run-next --queue-spec=$specArg --steal --out=$fileArg " . ($action === 's' ? '--skip' : '');
      if ($this->dryRun) {
        $io->writeln(sprintf("<info>DRY-RUN</info> cv %s", $runCmd));
        $queue->deleteItem($item);
      }
      else {
        $exitCode = Cv::passthru($runCmd);
        $state = $this->getState();
        if ($exitCode || !empty($state['is_error'])) {
          // WISHLIST: For interactive mode, perhaps allow retry/skip?
          $io->writeln('');
          $io->writeln(sprintf("<error>Error executing task: %s</error>", $task->title));
          if ($io->isDebug()) {
            $io->writeln('Subprocess results: ' . json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), OutputInterface::OUTPUT_PLAIN);
          }
          throw new QueueTaskException('Task returned error');
        }
      }
    }

    if ($io->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
      $io->newLine();
    }
  }

  protected static function formatTaskCallback(\CRM_Queue_Task $task) {
    $cb = implode('::', (array) $task->callback);
    $args = json_encode($task->arguments, JSON_UNESCAPED_SLASHES);
    return sprintf("%s(%s)", $cb, substr($args, 1, -1));
  }

}
