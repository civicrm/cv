<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for asking CiviCRM for the appropriate tarball to download.
 */
class UpgradeDbCommand extends BaseCommand {
  protected function configure() {
    $this
      ->setName('upgrade:db')
      ->setDescription('Run the database upgrade')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat())
      ->setHelp('Run the database upgrade

Examples:
  cv upgrade:db

Returns a JSON object with the properties:
  latestVer   The version to which you just upgraded
  message     A HTML version of the upgrade messages
  text        A plain-text version of the upgrade messages
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    // $result = \civicrm_api('Contact', 'get', array('version' => 3));
    //
    $codeVer = \CRM_Utils_System::version();
    $dbVer = \CRM_Core_BAO_Domain::version();
    if (version_compare($codeVer, $dbVer) == 0) {
      $result = array(
        'latestVer' => $codeVer,
        'message' => "You are already upgraded to CiviCRM $codeVer",
      );
      $result['text'] = $result['message'];
      $this->sendResult($input, $output, $result);
      return 0;
    }
    $upgradeHeadless = new \CRM_Upgrade_Headless();

    $result = $upgradeHeadless->run();

    $this->sendResult($input, $output, $result);
  }

}
