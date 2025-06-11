<?php
namespace Civi\Cv\Command;

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
      ->setDescription('Run the CiviCRM cron on the default domain (defaults to using the default domain organisation contact, or you can use a --user=USER)')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignore pending maintenance tasks. Force cron to run regardless of status.');
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

      $cid = \CRM_Core_DAO::singleValueQuery('SELECT contact_id FROM civicrm_domain ORDER BY id LIMIT 1');
      authx_login(['principal' => ['contactId' => $cid]]);
    }

    if (!$input->getOption('force') && $cronBlock = $this->getCronBlock()) {
      $output->writeln("<error>Cron skipped!</error> $cronBlock");
      return 0;
    }

    // Logging integration requires ~6.5 (or later).
    $jobManager = class_exists('CRM_Core_JobLogger')
      ? new \CRM_Core_JobManager($this->createLogger($output))
      : new \CRM_Core_JobManager();
    $jobManager->execute(FALSE);

    return 0;
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

  protected function createLogger(OutputInterface $output) {
    return new PsrLogger(new MultiLogger('cron', [
      new SymfonyConsoleLogger('cron', $output),
      new \CRM_Core_JobLogger(),
    ]));
  }

}
