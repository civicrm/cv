<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputInterface;
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
      ->setDescription('Run the CiviCRM cron on the default domain (defaults to using the default domain organisation contact, or you can use a --user=USER)');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (empty($input->getOption('user'))) {
      \CRM_Core_Config::singleton()->userPermissionTemp = new \CRM_Core_Permission_Temp();
      \CRM_Core_Config::singleton()->userPermissionTemp->grant('all CiviCRM permissions and ACLs');

      $cid = \CRM_Core_DAO::singleValueQuery('SELECT contact_id FROM civicrm_domain ORDER BY id LIMIT 1');
      authx_login(['principal' => ['contactId' => $cid]]);
    }

    $result = civicrm_api3('Job', 'execute', []);
    return empty($result['is_error']) ? 0 : 1;
  }

}
