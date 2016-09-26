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
  protected function configure() {
    $this
      ->setName('upgrade:get')
      ->setDescription('Find out what file you should use to upgrade')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat())
      ->addOption('stability', 's', InputOption::VALUE_REQUIRED, 'Specify the stability of the version to get (beta, rc, stable)', 'stable')
      ->addOption('cms', 'c', InputOption::VALUE_REQUIRED, 'Specify the cms to get (backdrop, drupal, drupal6, joomla, wordpress)', 'drupal')
      ->setHelp('Find out what file you should use to upgrade

Examples:
  cv upgrade:get --stability=rc --cms=wordpress

Returns a JSON object with the properties:
  version    the version number
  path       the path to download a tarball/zipfile
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $result = array(
      'version' => NULL,
      'path' => NULL,
    );
    switch ($input->getOption('stability')) {
      case 'beta':
        // get the beta
        break;

      case 'rc':
        // get the rc
        break;

      case 'stable':
      default:
        // get the stable version
    }

    $this->sendResult($input, $output, $result);
  }

}
