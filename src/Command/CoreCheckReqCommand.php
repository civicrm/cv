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
    $this->configureBootOptions('none');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $filterSeverities = $this->parseFilter($input);

    $showBootMsgsByDefault = in_array($input->getOption('out'), ['table', 'pretty']);
    $setup = $this->bootSetupSubsystem($input, $output, $showBootMsgsByDefault ? 0 : OutputInterface::VERBOSITY_VERBOSE);
    $reqs = $setup->checkRequirements();
    $messages = array_filter($reqs->getMessages(), function ($m) use ($filterSeverities) {
      return in_array($m['severity'], $filterSeverities);
    });
    uasort($messages, function ($a, $b) {
      return strcmp(
        $a['severity'] . '-' . $a['section'] . '-' . $a['name'],
        $b['severity'] . '-' . $b['section'] . '-' . $b['name']
      );
    });
    $this->sendTable($input, $output, $messages, array('severity', 'section', 'name', 'message'));
    return $reqs->getErrors() ? 1 : 0;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   */
  protected function parseFilter(InputInterface $input) {
    $filterSeverities = array();
    if ($input->getOption('filter-warnings')) {
      $filterSeverities[] = 'warning';
    }
    if ($input->getOption('filter-errors')) {
      $filterSeverities[] = 'error';
    }
    if ($input->getOption('filter-infos')) {
      $filterSeverities[] = 'info';
    }
    if ($filterSeverities === array()) {
      $filterSeverities = array('warning', 'error', 'info');
    }
    return $filterSeverities;
  }

}
