<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ScriptCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('php-script')
      ->setAliases(array('scr'))
      ->setDescription('Execute a PHP script')
      ->addArgument('script', InputArgument::REQUIRED);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    require $input->getArgument('script');
  }

}
