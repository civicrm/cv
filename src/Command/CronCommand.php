<?php
namespace Civi\Cv\Command;

use Civi\Cv\Log\InternalLogger;
use Civi\Cv\Log\MultiLogger;
use Civi\Cv\Log\SymfonyConsoleLogger;
use Civi\Cv\Util\PsrLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CronCommand extends CvCommand {

  /**
   * @var array
   */
  public $defaults;

  protected function configure() {
    $this
      ->setName('core:cron')
      ->setAliases(['cron'])
      ->setDescription('Execute scheduled and recurring jobs')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignore pending maintenance tasks. Force cron to run regardless of status.')
      ->setHelp('
<info>cv cron</info> command is designed for use as a <comment>cron job</comment>. It executes scheduled and recurring tasks, including all enabled jobs from <info>civicrm_job</info> table.

<comment>Identity and Permissions:</comment>

For purposes of logging and permissions, any cron invocation must be linked to a <info>Contact</info> record.

By default, <info>cv cron</info> executes as the <comment>default domain contact</comment> with super-user permissions. You may override this with <info>--user=USER</info>.

<comment>Console Output</comment>

Traditional cron systems send an alert if a cronjob displays output. Therefore, the default output for <info>cv cron</info> is fairly quiet. The exact behavior may be tuned:

* (CiviCRM v6.5+) By default, only display errors.
* (CiviCRM v6.5+) Use <info>-q</info> (<info>--quiet</info>) to suppress all output (including errors).
* (CiviCRM v6.5+) Use <info>-vv</info> to enable very verbose output (for monitoring, debugging).
* (CiviCRM, before v6.5) Strictly quiet, with no options to adjust output.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (empty($input->getOption('user'))) {

      // Grant access to all permissions for the current process.
      // Ex: `Job.process_mailing` needs permission to lookup recipients.
      \CRM_Core_Config::singleton()->userPermissionTemp = new class extends \CRM_Core_Permission_Temp {

        public function check($perm) {
          return TRUE;
        }

      };

      $cid = NULL;
      if ($domain = \CRM_Core_BAO_Domain::getDomain()) {
        $cid = $domain->contact_id;
      }
      if (!$cid) {
        $cid = \CRM_Core_DAO::singleValueQuery('SELECT contact_id FROM civicrm_domain ORDER BY id LIMIT 1');
      }
      authx_login(['principal' => ['contactId' => $cid]]);
    }

    if (!$input->getOption('force') && $cronBlock = $this->getCronBlock()) {
      $output->writeln("<error>Cron skipped!</error> $cronBlock");
      return 0;
    }

    // Logging integration requires ~6.5 (or later).
    $jobManager = class_exists('CRM_Core_JobLogger')
      ? new \CRM_Core_JobManager(new PsrLogger($loggers = $this->createLogger($output)))
      : new \CRM_Core_JobManager();
    $jobManager->execute(FALSE);

    $hasError = isset($loggers) ? $loggers->getLogger('summary')->hasError : FALSE;
    return ($hasError && !$output->isQuiet()) ? 1 : 0;
  }

  protected function getCronBlock(): ?string {
    $domainVersion = \CRM_Core_BAO_Domain::getDomain()->version;
    $codeVersion = \CRM_Utils_System::version();
    if (version_compare($domainVersion, $codeVersion, '<')) {
      return "Database needs upgrade from $domainVersion to $codeVersion.";
    }

    if (method_exists('CRM_Utils_System', ' isMaintenanceMode()')) {
      if (\CRM_Utils_System::isMaintenanceMode()) {
        return 'System is in maintenance mode';
      }
    }

    return NULL;
  }

  protected function createLogger(OutputInterface $output): MultiLogger {
    $topic = 'cron';

    $summary = new class ($topic) extends InternalLogger {

      /**
       * @var bool
       */
      public $hasError = FALSE;

      public function log($level, $message, array $context = array()) {
        if (in_array($level, ['error', 'critical', 'alert', 'emergency'])) {
          $this->hasError = TRUE;
        }
      }

    };

    return new MultiLogger($topic, [
      'console' => new SymfonyConsoleLogger($topic, $output),
      'db' => new \CRM_Core_JobLogger(),
      'summary' => $summary,
    ]);
  }

}
