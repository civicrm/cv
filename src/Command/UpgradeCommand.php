<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpgradeCommand extends CvCommand {

  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('upgrade')
      ->setDescription('Download CiviCRM and upgrade the site')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (args,json)', 'args')
      ->configureOutputOptions()
      ->addOption('stability', 's', InputOption::VALUE_REQUIRED, 'Specify the stability of the version to get (nightly, rc, stable)', 'stable')
      ->setHelp('Download CiviCRM, extract in place, and upgrade the site, notifying civicrm.org
Examples:
  cv upgrade --stability=rc
');
    // parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {

    throw new \RuntimeException("FIXME: Calls to run() should be escaped, e.g. with Process::sprintf()");

    $exitCode = 0;
    $stage = 'Start';
    $result = array();
    $reportName = \Civi\Cv\Util\Rand::createName();
    try {
      $stability = $input->getOption('stability');
      $stage = 'Lookup';
      $dl = \Civi\Cv\Util\Cv::run("upgrade:get --stability=$stability");
      $result['dl-data'] = $dl;
      if (!empty($dl['error'])) {
        throw new \RuntimeException($dl['error'], 1);
      }
      $stage = 'Start report';
      $startReport = \Civi\Cv\Util\Cv::run("upgrade:report --started --revision={$dl['rev']} --name=$reportName");
      $stage = 'Download and extract';
      $extract = \Civi\Cv\Util\Cv::run("upgrade:dl --url={$dl['url']}");
      $result['extract-data'] = $extract;
      $stage = 'Download report';
      $dlReport = \Civi\Cv\Util\Cv::run("upgrade:report --downloaded --download-url {$dl['url']} --extracted --name $reportName");
      $stage = 'Database upgrade';
      $db = \Civi\Cv\Util\Cv::run("upgrade:db");
      $result['db-upgrade'] = $db;
      $messageFile = sys_get_temp_dir() . "/upgrademessages-$reportName.html";
      file_put_contents($messageFile, $db['message']);
      $stage = 'Upgrade report';
      $finishReport = \Civi\Cv\Util\Cv::run("upgrade:report --upgraded --upgrade-messages $messageFile --name $reportName");

      // clean up
      $stage = 'Cleanup';
      unlink($messageFile);
    }
    catch (\RuntimeException $e) {
      $exitCode = 1;
      $result['error-stage'] = $stage;
      $result['error-message'] = $e->getMessage();
      $problem = "$stage problem: {$result['error_message']}";
      $result['fail-report'] = \Civi\Cv\Util\Cv::run("upgrade:report --failed --problem-message '$problem' --name $reportName");
    }

    $this->sendResult($input, $output, $result);
    return $exitCode;
  }

}
