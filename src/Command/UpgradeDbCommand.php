<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\BootTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Civi\Cv\Util\ConsoleQueueRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Command for asking CiviCRM for the appropriate tarball to download.
 */
class UpgradeDbCommand extends BaseCommand {

  use BootTrait;
  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('upgrade:db')
      ->setDescription('Run the database upgrade')
      ->configureOutputOptions(['fallback' => 'pretty'])
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
    if (!defined('CIVICRM_UPGRADE_ACTIVE')) {
      define('CIVICRM_UPGRADE_ACTIVE', 1);
    }
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

      if ($input->getOption('skip')) {
        $item = $queue->stealItem();
        $output->writeln(sprintf("<error>Skip task: %s</error>", $item->data->title));
        $queue->deleteItem($item);
      }

    }

    $output->writeln("<info>Executing upgrade...</info>", $niceMsgVerbosity);
    $runner = new ConsoleQueueRunner($input, $output, $queue, $input->getOption('dry-run'));
    $runner->runAll();

    $output->writeln("<info>Finishing upgrade...</info>", $niceMsgVerbosity);
    if (!$input->getOption('dry-run')) {
      \CRM_Upgrade_Form::doFinish();
    }

    $output->writeln("<info>Upgrade to <comment>$codeVer</comment> completed.</info>", $niceMsgVerbosity);

    if (version_compare($codeVer, '5.26.alpha', '<')) {
      // Work-around for bugs like dev/core#1713.
      // Note that #1713 didn't affect earlier versions of `cv` because they mistakenly omitted CIVICRM_UPGRADE_ACTIVE.
      $output->writeln('<info>Detected CiviCRM 5.25 or earlier. Force flush.</info>');
      \Civi\Cv\Util\Cv::passthru("flush");
    }

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

}
