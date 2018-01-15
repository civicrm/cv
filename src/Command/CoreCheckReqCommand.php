<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class CoreCheckReqCommand extends BaseCommand {

  use \Civi\Cv\Util\SetupCommandTrait;
  use \Civi\Cv\Util\DebugDispatcherTrait;

  protected function configure() {
    $this
      ->setName('core:check-req')
      ->setDescription('Check installation requirements')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getTabularFormats()) . ')', Encoder::getDefaultFormat('table'))
      ->addOption('filter-warnings', 'w', InputOption::VALUE_NONE, 'Show warnings')
      ->addOption('filter-errors', 'e', InputOption::VALUE_NONE, 'Show errors')
      ->addOption('filter-infos', 'i', InputOption::VALUE_NONE, 'Show info')
      ->configureSetupOptions()
      ->setHelp('
Check whether installation requirements are met.

Example: Show all checks
$ cv core:check-req

Example: Show only errors
$ cv core:check-req -e

Example: Show warnings and errors
$ cv core:check-req -we
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $filterLevels = $this->parseFilter($input);

    $showBootMsgsByDefault = in_array($input->getOption('out'), ['table', 'pretty']);
    $setup = $this->bootSetupSubsystem($input, $output, $showBootMsgsByDefault ? 0 : OutputInterface::VERBOSITY_VERBOSE);
    $reqs = $setup->checkRequirements();
    $messages = array_filter($reqs->getMessages(), function ($m) use ($filterLevels) {
      return in_array($m['level'], $filterLevels);
    });
    usort($messages, function($a, $b){
      $nameCmp = strcmp($a['name'], $b['name']);
      return $nameCmp;
    });
    $this->sendTable($input, $output, $messages, array('level', 'name', 'message'));
    return $reqs->getErrors() ? 1 : 0;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   */
  protected function parseFilter(InputInterface $input) {
    $filterLevels = array();
    if ($input->getOption('filter-warnings')) {
      $filterLevels[] = 'warning';
    }
    if ($input->getOption('filter-errors')) {
      $filterLevels[] = 'error';
    }
    if ($input->getOption('filter-infos')) {
      $filterLevels[] = 'info';
    }
    if ($filterLevels === array()) {
      $filterLevels = array('warning', 'error', 'info');
      return $filterLevels;
    }
    return $filterLevels;
  }

}
