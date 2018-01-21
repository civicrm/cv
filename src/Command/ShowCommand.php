<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\SiteConfigReader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;

  protected function configure() {
    $this
      ->setName('vars:show')
      ->setDescription('Show the configuration of the local CiviCRM installation')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat());
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    $reader = new SiteConfigReader(CIVICRM_SETTINGS_PATH);
    $data = $reader->compile(array('buildkit', 'home', 'active'));
    $this->sendResult($input, $output, $data);
  }

}
