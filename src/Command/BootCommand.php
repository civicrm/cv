<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class BootCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('php-boot')
      ->setDescription('Generate PHP bootstrap code');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    $code = \Civi\Cv\Bootstrap::singleton()->generate()
      . '\CRM_Core_Config::singleton();'
      . '\CRM_Utils_System::loadBootStrap(array(), FALSE);';
    $output->writeln($code);
  }

}
