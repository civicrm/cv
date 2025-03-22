<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\StructuredOutputTrait;
use Civi\Cv\Util\ConsoleQueueRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for asking CiviCRM for the appropriate tarball to download.
 */
class UpgradeDbCommand extends CvCommand {

  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('upgrade:db')
      ->setAliases(['updb'])
      ->setDescription('Run the database upgrade')
      ->configureOutputOptions(['fallback' => 'pretty'])
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Preview the list of upgrade tasks')
      ->addOption('retry', NULL, InputOption::VALUE_NONE, 'Resume a failed upgrade, retrying the last step')
      ->addOption('skip', NULL, InputOption::VALUE_NONE, 'Resume a failed upgrade, skipping the last step')
      ->addOption('step', NULL, InputOption::VALUE_NONE, 'Run the upgrade queue in steps, pausing before each step')
      ->addOption('mode', NULL, InputOption::VALUE_REQUIRED, 'Mode to run upgrade (auto|full|ext)', 'auto')
      ->setHelp('Run the database upgrade

Examples:
  cv upgrade:db
  cv upgrade:db --dry-run
  cv upgrade:db --retry
');
  }

  /**
   * @var \Symfony\Component\Console\Input\InputInterface
   */
  protected $input;

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  protected $output;

  protected $niceVerbosity;

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    if (!defined('CIVICRM_UPGRADE_ACTIVE')) {
      define('CIVICRM_UPGRADE_ACTIVE', 1);
    }
    parent::initialize($input, $output);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!ini_get('safe_mode')) {
      set_time_limit(0);
    }

    if ($input->getOption('step')) {
      if ($input->getOption('out') !== 'pretty') {
        throw new \RuntimeException('The --step option only works with "pretty" output.');
      }
      if ($output->getVerbosity() < OutputInterface::VERBOSITY_VERY_VERBOSE) {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
      }
    }

    $this->niceVerbosity = $input->getOption('out') === 'pretty' ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE;
    $isFirstTry = !$input->getOption('retry') && !$input->getOption('skip');

    $codeVer = \CRM_Utils_System::version();
    $dbVer = \CRM_Core_BAO_Domain::version();
    $postUpgradeMessageFile = $this->getUpgradeFile();
    $output->writeln(sprintf("<info>Found CiviCRM database version <comment>%s</comment>.</info>", $dbVer), $this->niceVerbosity);
    $output->writeln(sprintf("<info>Found CiviCRM code version <comment>%s</comment>.</info>", $codeVer), $this->niceVerbosity);

    if ($isFirstTry) {
      file_put_contents($postUpgradeMessageFile, "");
      chmod($postUpgradeMessageFile, 0700);
    }
    if (!$isFirstTry && !file_exists($postUpgradeMessageFile)) {
      throw new \Exception("Cannot resume upgrade: The log file ($postUpgradeMessageFile) is missing. Consider a regular upgrade (without --retry or --skip).");
    }

    $result = 0;
    $mode = $input->getOption('mode');
    if ($mode === 'auto') {
      $mode = (version_compare($dbVer, $codeVer, '<') ? 'full' : 'ext');
    }

    if ($mode === 'full') {
      $result += $this->runCoreUpgrade($isFirstTry, $dbVer, $postUpgradeMessageFile, $codeVer);
      $this->sendMessages($postUpgradeMessageFile, $codeVer);
    }
    elseif ($mode === 'ext') {
      $result += $this->runExtensionUpgrade($isFirstTry);
    }

    $output->writeln("<info>Have a nice day.</info>");
    return $result;
  }

  /**
   * Determine the path to the upgrade messages file.
   *
   * @return string
   *   The full path to the upgrade data file.
   *   This path should be reproducible, so that the failed+resumed
   *   upgrades use the same file.
   */
  protected function getUpgradeFile() {
    $home = getenv('XDG_STATE_HOME');
    if (empty($home) || !file_exists($home)) {
      $home = getenv('HOME') ? getenv('HOME') : getenv('USERPROFILE');
      if (empty($home) || !file_exists($home)) {
        throw new \RuntimeException("Failed to locate HOME or USERPROFILE");
      }
    }

    $dir = implode(DIRECTORY_SEPARATOR, [$home, '.cv', 'upgrade']);
    if (!file_exists($dir)) {
      if (!mkdir($dir, 0777, TRUE)) {
        throw new \RuntimeException("Failed to initialize upgrade data folder: $dir");
      }
    }

    $id = md5(implode(\CRM_Core_DAO::VALUE_SEPARATOR, array(
      function_exists('posix_getuid') ? posix_getuid() : 0,
      $home,
      CIVICRM_SETTINGS_PATH,
      $GLOBALS['civicrm_root'],

      // e.g. one codebase, multi database
      parse_url(CIVICRM_DSN, PHP_URL_PATH),

      // e.g. CMS vs extern vs installer
      \CRM_Utils_Array::value('SCRIPT_FILENAME', $_SERVER, ''),

      // e.g. name-based vhosts
      \CRM_Utils_Array::value('HTTP_HOST', $_SERVER, ''),

      // e.g. port-based vhosts
      \CRM_Utils_Array::value('SERVER_PORT', $_SERVER, ''),
    )));

    return $dir . DIRECTORY_SEPARATOR . $id . '.dat';
  }

  /**
   * @param bool $isFirstTry
   * @param string $dbVer
   * @param string $postUpgradeMessageFile
   * @param string $codeVer
   *
   * @return int|null
   * @throws \CRM_Core_Exception
   */
  protected function runCoreUpgrade(bool $isFirstTry, string $dbVer, string $postUpgradeMessageFile, string $codeVer): ?int {
    $input = $this->input;
    $output = $this->output;

    if ($isFirstTry && FALSE !== stripos($dbVer, 'upgrade')) {
      throw new \Exception("Cannot begin upgrade: The database indicates that an incomplete upgrade is pending. If you would like to resume, use --retry or --skip.");
    }

    $upgrade = new \CRM_Upgrade_Form();

    if ($error = $upgrade->checkUpgradeableVersion($dbVer, $codeVer)) {
      throw new \Exception($error);
    }

    if ($isFirstTry) {
      $output->writeln("<info>Checking pre-upgrade messages...</info>", $this->niceVerbosity);
      $preUpgradeMessage = NULL;
      $upgrade->setPreUpgradeMessage($preUpgradeMessage, $dbVer, $codeVer);
      if ($preUpgradeMessage) {
        $output->writeln(\CRM_Utils_String::htmlToText($preUpgradeMessage), $this->niceVerbosity);
        if (!\Civi\Cv\Cv::io()->confirm('Continue?')) {
          $output->writeln("<error>Abort</error>");
          return 1;
        }
      }
      else {
        $output->writeln("(No messages)", $this->niceVerbosity);
      }
    }

    // Why is dropTriggers() hard-coded? Can't we just enqueue this as part of buildQueue()?
    if ($isFirstTry) {
      $output->writeln("<info>Dropping SQL triggers...</info>", $this->niceVerbosity);
      if (!$input->getOption('dry-run')) {
        \CRM_Core_DAO::dropTriggers();
      }
    }

    if ($isFirstTry) {
      $output->writeln("<info>Preparing upgrade...</info>", $this->niceVerbosity);
      $queue = \CRM_Upgrade_Form::buildQueue($dbVer, $codeVer, $postUpgradeMessageFile);
      $this->assertResumableQueue($queue);
    }
    else {
      $output->writeln("<info>Resuming upgrade...</info>", $this->niceVerbosity);
      $queue = \CRM_Queue_Service::singleton()->load([
        'name' => \CRM_Upgrade_Form::QUEUE_NAME,
        'type' => 'Sql',
      ]);

      if ($input->getOption('skip')) {
        $item = $queue->stealItem();
        $output->writeln(sprintf("<error>Skip task: %s</error>", $item->data->title));
        $queue->deleteItem($item);
      }
    }

    $output->writeln("<info>Executing upgrade...</info>", $this->niceVerbosity);
    $runner = new ConsoleQueueRunner(\Civi\Cv\Cv::io(), $queue, $input->getOption('dry-run'), $input->getOption('step'));
    $runner->runAll();

    $output->writeln("<info>Finishing upgrade...</info>", $this->niceVerbosity);
    if (!$input->getOption('dry-run')) {
      \CRM_Upgrade_Form::doFinish();
    }

    if (version_compare($codeVer, '5.53.alpha1', '<')) {
      // Note: Before 5.53+, core-upgrade didn't touch extensions.
      $this->runExtensionUpgrade($isFirstTry);
    }

    $output->writeln("<info>Upgrade to <comment>$codeVer</comment> completed.</info>", $this->niceVerbosity);

    if (version_compare($codeVer, '5.26.alpha', '<')) {
      // Work-around for bugs like dev/core#1713.
      // Note that #1713 didn't affect earlier versions of `cv` because they mistakenly omitted CIVICRM_UPGRADE_ACTIVE.
      $output->writeln('<info>Detected CiviCRM 5.25 or earlier. Force flush.</info>');
      \Civi\Cv\Util\Cv::passthru("flush");
    }

    return 0;
  }

  protected function sendMessages(string $postUpgradeMessageFile, string $codeVer): void {
    $input = $this->input;
    $output = $this->output;

    $output->writeln("<info>Checking post-upgrade messages...</info>", $this->niceVerbosity);
    $message = file_get_contents($postUpgradeMessageFile);
    if ($input->getOption('out') === 'pretty') {
      if ($message) {
        $output->writeln(\CRM_Utils_String::htmlToText($message), OutputInterface::OUTPUT_RAW);
      }
      else {
        $output->writeln("(No messages)", $this->niceVerbosity);
      }
    }
    else {
      $this->sendResult($input, $output, [
        'latestVer' => $codeVer,
        'message' => $message,
        'text' => \CRM_Utils_String::htmlToText($message),
      ]);
    }
    unlink($postUpgradeMessageFile);
  }

  protected function runExtensionUpgrade(bool $isFirstTry): int {
    // `cv upgrade:db` started with CIVICRM_UPGRADE_ACTIVE, which means that the system
    // booted with a narrow dispatch policy (preventing extensions from mucking with core-upgrade).
    // But we've now decided we don't need full core-upgrade. So we can use an ordinary environment.
    \Civi::dispatcher()->setDispatchPolicy(NULL);

    $input = $this->input;
    $output = $this->output;

    if ($isFirstTry) {
      $output->writeln("<info>Preparing extension upgrade...</info>", $this->niceVerbosity);
      \CRM_Core_Invoke::rebuildMenuAndCaches(TRUE);
      $queue = \CRM_Extension_Upgrades::createQueue();
      $this->assertResumableQueue($queue);
    }
    else {
      $output->writeln("<info>Resuming extension upgrade...</info>", $this->niceVerbosity);
      $queue = \CRM_Queue_Service::singleton()->load([
        'name' => \CRM_Extension_Upgrades::QUEUE_NAME,
        'type' => 'Sql',
      ]);

      if ($input->getOption('skip')) {
        $item = $queue->stealItem();
        $output->writeln(sprintf("<error>Skip task: %s</error>", $item->data->title));
        $queue->deleteItem($item);
      }
    }

    $runner = new ConsoleQueueRunner(\Civi\Cv\Cv::io(), $queue, $input->getOption('dry-run'), $input->getOption('step'));
    $runner->runAll();
    return 0;
  }

  /**
   * @param \CRM_Queue_Service $queue
   *
   * @return void
   */
  protected function assertResumableQueue($queue): void {
    if (!($queue instanceof \CRM_Queue_Queue_Sql)) {
      // Sanity check -- only SQL queues are resuamble.
      throw new \RuntimeException("Error: \"cv upgrade\" only supports SQL-based queues.");
    }
  }

}
