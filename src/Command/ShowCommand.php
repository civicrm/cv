<?php
namespace Civi\Cv\Command;

use Civi\Cv\SiteConfigReader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;
  use \Civi\Cv\Util\StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('vars:show')
      ->setDescription('Show the configuration of the local CiviCRM installation')
      ->configureOutputOptions();
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    $reader = new SiteConfigReader(CIVICRM_SETTINGS_PATH);
    $data = $reader->compile(array('buildkit', 'home', 'active'));
    $this->sendResult($input, $output, $data);
  }

}
