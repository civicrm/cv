<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class EvalCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('php-eval')
      ->setAliases(array('ev'))
      ->setDescription('Evaluate a snippet of PHP code')
      ->addArgument('code')
        ->addOption('out', NULL, InputArgument::OPTIONAL, 'Specify return format (auto,json,none,php,pretty,shell)', 'auto')
      ->setHelp('
Evaluate a snippet of PHP code

Examples:
  cv ev \'civicrm_api3("System", "flush", array());\'
  cv ev \'if (rand(0,10)<5) echo "heads\n"; else echo "tails\n";\'

When reading data, you may use "return":
  cv ev \'return CRM_Utils_System::version()\'
  cv ev \'return CRM_Utils_System::version()\' --out=shell
  cv ev \'return CRM_Utils_System::version()\' --out=json

If the output format is set to "auto". This will be produce silent output -- unless
you use a "return" statement. In that case, it will use the default (' . Application::getDefaultOut() . ').

NOTE: To change the default output format, set CV_OUTPUT.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    if ($input->getOption('out') === 'auto') {
      $hasReturn = preg_match('/^\s*return[ \t\r\n]/', $input->getArgument('code'))
      || preg_match('/[;\{]\s*return[ \t\r\n]/', $input->getArgument('code'));
      $input->setOption('out', $hasReturn ? Application::getDefaultOut() : 'none');
    }

    $value = eval($input->getArgument('code') . ';');
    $this->sendResult($input, $output, $value);
  }

}
