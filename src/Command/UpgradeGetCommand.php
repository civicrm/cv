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
class UpgradeGetCommand extends BaseCommand {
  const DEFAULT_CHECK_URL = "https://upgrade.civicrm.org/check";

  protected function configure() {
    $this
      ->setName('upgrade:get')
      ->setDescription('Find out what file you should use to upgrade')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat())
      ->addOption('stability', 's', InputOption::VALUE_REQUIRED, 'Specify the stability of the version to get (beta, rc, stable)', 'stable')
      ->addOption('cms', 'c', InputOption::VALUE_REQUIRED, 'Specify the cms to get (Backdrop, Drupal, Drupal6, Joomla, Wordpress) instead of the current site')
      ->setHelp('Find out what file you should use to upgrade

Examples:
  cv upgrade:get --stability=rc

Returns a JSON object with the properties:
  rev        a unique ID corresponding to the commits that are included
  path       the path to download a tarball/zipfile
  git        the corresponding commits of the civicrm repos
  vars       the site variables from cv vars:show
  error      only appears if there is an error
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $result = array();
    $exitCode = 0;
    $stability = $input->getOption('stability');
    $cms = $input->getOption('cms');
    if (empty($cms)) {
      $this->boot($input, $output);
      if (defined('CIVICRM_UF')) {
        $cms = CIVICRM_UF;
      }
      $result['vars'] = $GLOBALS['_CV']; // REMOVE
    }
    if (empty($cms)){
      throw new \RuntimeException("Cannot determine download URL without CMS");
    }

    $url = self::DEFAULT_CHECK_URL . "?stability=$stability";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $lookup = curl_exec($ch);
    curl_close($ch);
    $lookup = json_decode($lookup, TRUE);

    if (empty($lookup)) {
      $result = array(
        'error' => "Version not found at $url",
      );
      $exitCode = 1;
    }
    else {
      if (array_key_exists('rev', $lookup)) {
        $result['rev'] = $lookup['rev'];
      }
      if (array_key_exists('git', $lookup)) {
        $result['git'] = $lookup['git'];
      }
      if (!empty($lookup['tar'][$cms])) {
        $result['path'] = $lookup['tar'][$cms];
      }
    }

    $this->sendResult($input, $output, $result);
    return $exitCode;
  }

}
