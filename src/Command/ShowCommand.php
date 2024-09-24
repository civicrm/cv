<?php
namespace Civi\Cv\Command;

use Civi\Cv\SiteConfigReader;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends CvCommand {

  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('vars:show')
      ->setDescription('Show the configuration of the local CiviCRM installation')
      ->configureOutputOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $reader = new SiteConfigReader(CIVICRM_SETTINGS_PATH);
    $data = $reader->compile(array('buildkit', 'home', 'active'));
    $this->sendResult($input, $output, $data);
    return 0;
  }

}
