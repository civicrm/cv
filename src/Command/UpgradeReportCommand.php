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
class UpgradeReportCommand extends BaseCommand {
  const DEFAULT_REPORT_URL = 'https://upgrade.civicrm.org/report';

  const REPORT_MODES = array(
    'started',
    'downloaded',
    'extracted',
    'upgraded',
    'finished',
    'problem',
  );

  protected function configure() {
    $this
      ->setName('upgrade:report')
      ->setDescription('Notify civicrm.org of your upgrade success or failure')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat())
      ->addOption('name', NULL, InputOption::VALUE_REQUIRED, 'Specify the name to link the report to past reports on the same upgrade')
      ->addOption('downloadurl', NULL, InputOption::VALUE_REQUIRED, 'Indicate the URL for the download attempt')
      ->addOption('upgrademessages', NULL, InputOption::VALUE_REQUIRED, 'Provide array of upgrade messages and version')
      ->addOption('problemmessage', NULL, InputOption::VALUE_REQUIRED, 'Provide a message about the problem')
      ->addOption('reporter', NULL, InputOption::VALUE_REQUIRED, "Your email address so you can be contacted with questions")
      ->setHelp('Notify civicrm.org of your upgrade success or failure

Examples:
  cv upgrade:report --started=1475079931 --downloaded

Returns a JSON object with the properties:
  name      The name under which the report was issued

');

    foreach (self::REPORT_MODES as $mode) {
      $this->addOption($mode, NULL, InputOption::VALUE_OPTIONAL, "Send a \"$mode\" report, optionally with a timestamp");
    }

    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    // POST https://upgrade.civicrm.org/report
    // Content-type: application/x-www-form-urlencoded
    //
    // `name`          Always required.
    // `site_id`       An identifier of the site that carries over between upgrades
    // `reporter`      An email address for contacting someone.
    // `status`        'running', 'successful', or 'failed'
    // `stage`         Read-only value computed from timestamps.
    // `downloadUrl`   The URL used to setup [[WRITE-ONCE]]
    // `started`       Time (seconds since epoch) at which we generated the start report  [[WRITE-ONCE]]
    // `startReport`   JSON report from "System.get" [[WRITE-ONCE]]
    // `downloaded`    Time (seconds since epoch) at which the download completed[[WRITE-ONCE]]
    // `extracted`     Time (seconds since epoch) at which the tarball finished extraction [[WRITE-ONCE]]
    // `upgraded`      Time (seconds since epoch) at which the DB upgrade  [[WRITE-ONCE]]
    // `upgradeReport` List of upgrade messages [[WRITE-ONCE]]
    // `finished`      Time (seconds since epoch) at which we generated the finish report [[WRITE-ONCE]]
    // `finishReport`  JSON report from "System.get" [[WRITE-ONCE]]
    // `testsReport`   JSON report from a testing suite

    $report = array(
      'cvVersion' => '@package_version@',
    );

    // Figure mode(s) for the report
    $reportProblems = $modes = array();
    foreach (self::REPORT_MODES as $mode) {
      if (!$input->hasParameterOption("--$mode")) {
        continue;
      }
      $modeTime = $input->getOption($mode) ?: time();

      $report[$mode] = $modes[$mode] = $modeTime;
    }

    // Check required fields for the mode(s)
    $requirements = array(
      'downloaded' => array(
        'downloadurl' => array(
          'label' => 'download URL',
          'reportkey' => 'downloadurl',
        ),
      ),
      'upgraded' => array(
        'upgrademessages' => array(
          'label' => 'upgrade messages',
          'reportkey' => 'upgradeReport',
        ),
      ),
      'problem' => array(
        'problemmessage' => array(
          'label' => 'problem message',
          'reportkey' => 'problemmessage',
        ),
      ),
    );
    foreach ($requirements as $reqMode => $reqs) {
      foreach ($reqs as $reqOpt => $req) {
        if ($input->hasParameterOption("--$reqOpt")) {
          $report[$req['reportkey']] = $input->getOption($reqOpt);
        }
        elseif (array_key_exists($reqMode, $modes)) {
          $reportProblems[] = "You must specify the {$req['label']} as --$reqOpt.";
        }
      }
    }

    // Find or create name
    $initialModes = array(
      'started',
      'problem',
    );
    if (!($report['name'] = $input->getOption('name'))) {
      if (empty(array_intersect($initialModes, array_keys($modes)))) {
        $reportProblems[] = 'Unless you are sending a start report (with --started or --problem), you must specify the report name (with --name)';
      }
      else {
        $report['name'] = $this->createName($report);
      }
    }

    // Check that we're not trying to report a start when it's too late
    if (array_key_exists('started', $modes)) {
      $tooLate = array(
        'extracted',
        'upgraded',
        'finished',
      );
      if (!empty(array_intersect($tooLate, array_keys($modes)))) {
        $reportProblems[] = "You can't report a start once you have extracted or upgraded. Use --problem instead.";
      }
    }

    // For now, just throw an exception if the report is bad.
    if (!empty($reportProblems)) {
      throw new \RuntimeException(implode("\n", $reportProblems));
    }

    // Set up identity of report
    $report['siteId'] = \Civi\Cv\Util\Cv::run("ev \"return md5('We need to talk about your TPS reports' . CIVICRM_SITE_KEY);\" --level=settings");

    if ($input->hasParameterOption('--reporter')) {
      $report['reporter'] = $input->getOption('reporter');
    }

    $reportPoints = array(
      'started',
      'upgraded',
      'finished',
    );

    $reportsToSend = array_intersect_key(array_fill_keys($reportPoints, NULL), $modes);

    if (!empty($reportsToSend)) {
      $systemReport = $this->systemReport();
      foreach ($reportsToSend as $stage => $x) {
        $key = preg_replace('/ed$/', 'Report', $stage);
        $report[$key] = $systemReport;
      }
    }

    if (!empty($report['problem'])) {
      $report['failed'] = $report['problem'];
      $report['problem'] = $input->getOption('problemmessage') ?: 'No problem message';
    }

    // Send report
    $report['response'] = $this->reportToCivi($report);

    $this->sendResult($input, $output, $report);
  }

  /**
   * Generates a name to identify the report
   *
   * @param array $report
   *   Report information (or anything, really)
   * @return string
   *   The name to use
   */
  protected function createName($report) {
    return md5(json_encode($report) . uniqid() . rand() . rand() . rand());
  }

  /**
   * Get a system report if available
   *
   * @return array
   *   A system report.
   */
  protected function systemReport() {
    try {
      $report = \Civi\Cv\Util\Cv::run('api system.get');
    }
    catch (\Exception $e) {
      $report = array('error' => 'Could not produce report');
    }
    $vars = \Civi\Cv\Util\Cv::run('vars:show');
    $domain = empty($vars['CMS_URL']) ? NULL : preg_replace(";https?://;", '', $vars['CMS_URL']);
    $this->recursiveRedact($report, $domain);
    return $report;
  }

  protected function recursiveRedact(&$report, $domain) {
    foreach ($report as $k => &$v) {
      if (is_array($v)) {
        $this->recursiveRedact($v, $domain);
      }
      elseif ($v == "REDACTED") {
        unset($report[$k]);
      }
      elseif ($domain) {
        $v = preg_replace(";(https?://)?$domain;", 'REDACTEDURL/', $v);
      }
    }
  }

  protected function reportToCivi($report) {
    foreach ($report as &$part) {
      if (is_array($part)) {
        $part = json_encode($part);
      }
    }
    $ch = curl_init(self::DEFAULT_REPORT_URL);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $report);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
  }

}
