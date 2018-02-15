<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Command for asking CiviCRM for the appropriate tarball to download.
 */
class UpgradeDbCommand extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;

  protected function configure() {
    $this
      ->setName('upgrade:db')
      ->setDescription('Run the database upgrade')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat('pretty'))
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Preview the list of upgrade tasks')
      ->addOption('retry', NULL, InputOption::VALUE_NONE, 'Resume a failed upgrade, retrying the last step')
      ->addOption('skip', NULL, InputOption::VALUE_NONE, 'Resume a failed upgrade, skipping the last step')
      ->setHelp('Run the database upgrade

Examples:
  cv upgrade:db
  cv upgrade:db --dry-run
  cv upgrade:db --retry
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    if (!ini_get('safe_mode')) {
      set_time_limit(0);
    }

    $niceMsgVerbosity = $input->getOption('out') === 'pretty' ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE;
    $isFirstTry = !$input->getOption('retry') && !$input->getOption('skip');

    $codeVer = \CRM_Utils_System::version();
    $dbVer = \CRM_Core_BAO_Domain::version();
    $postUpgradeMessageFile = $this->getUpgradeFile();
    $output->writeln(sprintf("<info>Found CiviCRM database version <comment>%s</comment>.</info>", $dbVer), $niceMsgVerbosity);
    $output->writeln(sprintf("<info>Found CiviCRM code version <comment>%s</comment>.</info>", $codeVer), $niceMsgVerbosity);

    if (version_compare($codeVer, $dbVer) == 0) {
      $result = array(
        'latestVer' => $codeVer,
        'message' => "You are already upgraded to CiviCRM $codeVer",
      );
      $result['text'] = $result['message'];
      $this->sendResult($input, $output, $result);
      return 0;
    }

    if ($isFirstTry && FALSE !== stripos($dbVer, 'upgrade')) {
      throw new \Exception("Cannot begin upgrade: The database indicates that an incomplete upgrade is pending. If you would like to resume, use --retry or --skip.");
    }
    if (!$isFirstTry && FALSE === stripos($dbVer, 'upgrade')) {
      throw new \Exception("Cannot resume upgrade: The database does not show a pending upgrade. Consider a regular upgrade (without --retry or --skip).");
    }
    if (!$isFirstTry && !file_exists($postUpgradeMessageFile)) {
      throw new \Exception("Cannot resume upgrade: The log file ($postUpgradeMessageFile) is missing. Consider a regular upgrade (without --retry or --skip).");
    }

    $upgrade = new \CRM_Upgrade_Form();

    if ($error = $upgrade->checkUpgradeableVersion($dbVer, $codeVer)) {
      throw new \Exception($error);
    }

    if ($isFirstTry) {
      $output->writeln("<info>Checking pre-upgrade messages...</info>", $niceMsgVerbosity);
      $preUpgradeMessage = NULL;
      $upgrade->setPreUpgradeMessage($preUpgradeMessage, $dbVer, $codeVer);
      if ($preUpgradeMessage) {
        $output->writeln(\CRM_Utils_String::htmlToText($preUpgradeMessage), $niceMsgVerbosity);
        if ($input->isInteractive() && $input->getOption('out') === 'pretty') {
          $helper = $this->getHelper('question');
          $question = new ConfirmationQuestion("\n<comment>Press ENTER to continue</comment>\n", TRUE);
          if (!$helper->ask($input, $output, $question)) {
            $output->writeln("<error>Abort</error>");
            return 1;
          }
        }
      }
      else {
        $output->writeln("(No messages)", $niceMsgVerbosity);
      }
    }

    // Why is dropTriggers() hard-coded? Can't we just enqueue this as part of buildQueue()?
    if ($isFirstTry) {
      $output->writeln("<info>Dropping SQL triggers...</info>", $niceMsgVerbosity);
      if (!$input->getOption('dry-run')) {
        \CRM_Core_DAO::dropTriggers();
      }
    }

    if ($isFirstTry) {
      $output->writeln("<info>Preparing upgrade...</info>", $niceMsgVerbosity);
      file_put_contents($postUpgradeMessageFile, "");
      chmod($postUpgradeMessageFile, 0700);
      $queue = \CRM_Upgrade_Form::buildQueue($dbVer, $codeVer, $postUpgradeMessageFile);

      if (!($queue instanceof \CRM_Queue_Queue_Sql)) {
        // Sanity check -- only SQL queues are resuamble.
        throw new \RuntimeException("Error: \"cv upgrade\" only supports SQL-based queues.");
      }
    }
    else {
      $output->writeln("<info>Resuming upgrade...</info>", $niceMsgVerbosity);
      $queue = \CRM_Queue_Service::singleton()->load(array(
        'name' => \CRM_Upgrade_Form::QUEUE_NAME,
        'type' => 'Sql',
      ));
    }

    $output->writeln("<info>Executing upgrade...</info>", $niceMsgVerbosity);

    if (!$input->getOption('dry-run')) {
      $queueResult = $this->runAllViaCLI($input, $output, $queue, $niceMsgVerbosity);
      if ($queueResult !== TRUE) {
        throw $queueResult['exception'];
      }
    }
    else {
      $this->previewAllViaCLI($input, $output, $queue, $niceMsgVerbosity);
    }

    $output->writeln("<info>Finishing upgrade...</info>", $niceMsgVerbosity);
    if (!$input->getOption('dry-run')) {
      \CRM_Upgrade_Form::doFinish();
    }

    $output->writeln("<info>Upgrade to <comment>$codeVer</comment> completed.</info>", $niceMsgVerbosity);

    $output->writeln("<info>Checking post-upgrade messages...</info>", $niceMsgVerbosity);
    $message = file_get_contents($postUpgradeMessageFile);
    if ($input->getOption('out') === 'pretty') {
      if ($message) {
        $output->writeln(\CRM_Utils_String::htmlToText($message), OutputInterface::OUTPUT_RAW);
      }
      else {
        $output->writeln("(No messages)", $niceMsgVerbosity);
      }
      $output->writeln("<info>Have a nice day.</info>", $niceMsgVerbosity);
    }
    else {
      $this->sendResult($input, $output, array(
        'latestVer' => $codeVer,
        'message' => $message,
        'text' => \CRM_Utils_String::htmlToText($message),
      ));
    }
    unlink($postUpgradeMessageFile);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \CRM_Queue_Queue $queue
   * @return bool
   */
  protected function runAllViaCLI(InputInterface $input, OutputInterface $output, $queue) {
    $queueRunner = new \CRM_Queue_Runner(array(
      'title' => ts('CiviCRM Upgrade Tasks'),
      'queue' => $queue,
    ));

    $taskResult = $queueRunner->formatTaskResult(TRUE);
    while ($taskResult['is_continue']) {
      // setRaiseException should't be necessary here, but there's a bug
      // somewhere which causes this setting to be lost.  Observed while
      // upgrading 4.0=>4.2.  This preference really shouldn't be a global
      // setting -- it should be more of a contextual/stack-based setting.
      // This should be appropriate because queue-runners are not used with
      // basic web pages -- they're used with CLI/REST/AJAX.
      $errorScope = \CRM_Core_TemporaryErrorScope::useException();
      $taskResult = $queueRunner->runNext();
      $output->writeln(sprintf("* <info>(Executed)</info> %s", $taskResult['last_task_title']), OutputInterface::VERBOSITY_VERBOSE);
      $errorScope = NULL;
    }

    if ($taskResult['numberOfItems'] == 0) {
      $result = $queueRunner->handleEnd();
      return TRUE;
    }
    else {
      return $taskResult;
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \CRM_Queue_Queue $queue
   * @return bool
   */
  protected function previewAllViaCLI(InputInterface $input, OutputInterface $output, $queue) {
    $queueRunner = new \CRM_Queue_Runner(array(
      'title' => ts('CiviCRM Upgrade Tasks'),
      'queue' => $queue,
    ));

    $taskResult = $queueRunner->formatTaskResult(TRUE);
    while ($taskResult['is_continue']) {
      // setRaiseException should't be necessary here, but there's a bug
      // somewhere which causes this setting to be lost.  Observed while
      // upgrading 4.0=>4.2.  This preference really shouldn't be a global
      // setting -- it should be more of a contextual/stack-based setting.
      // This should be appropriate because queue-runners are not used with
      // basic web pages -- they're used with CLI/REST/AJAX.
      $taskResult = $queueRunner->skipNext();
      $output->writeln(sprintf("* <info>(Dry Run)</info> %s", $taskResult['last_task_title']), OutputInterface::VERBOSITY_VERBOSE);
    }

    if ($taskResult['numberOfItems'] == 0) {
      $result = $queueRunner->handleEnd();
      return TRUE;
    }
    else {
      return $taskResult;
    }
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
    $home = getenv('HOME') ? getenv('HOME') : getenv('USERPROFILE');
    if (empty($home) || !file_exists($home)) {
      throw new \RuntimeException("Failed to locate HOME or USERPROFILE");
    }

    $dir = implode(DIRECTORY_SEPARATOR, [$home, '.cv', 'upgrade']);
    if (!file_exists($dir)) {
      if (!mkdir($dir, 0777, TRUE)) {
        throw new \RuntimeException("Failed to initialize upgrade data folder: $dir");
      }
    }

    $id = md5(implode(\CRM_Core_DAO::VALUE_SEPARATOR, array(
        posix_getuid(),
        CIVICRM_SETTINGS_PATH,
        $GLOBALS['civicrm_root'],
        parse_url(CIVICRM_DSN, PHP_URL_PATH), // e.g. one codebase, multi database
        \CRM_Utils_Array::value('SCRIPT_FILENAME', $_SERVER, ''), // e.g. CMS vs extern vs installer
        \CRM_Utils_Array::value('HTTP_HOST', $_SERVER, ''), // e.g. name-based vhosts
        \CRM_Utils_Array::value('SERVER_PORT', $_SERVER, ''), // e.g. port-based vhosts
      )));

    return $dir . DIRECTORY_SEPARATOR . $id . '.dat';
  }

}
