<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\SetupCommandTrait;
use Civi\Cv\Util\DebugDispatcherTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CoreCheckReqCommand extends CvCommand {

  use SetupCommandTrait;
  use DebugDispatcherTrait;
  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('core:check-req')
      ->setDescription('Check installation requirements')
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'table', 'shortcuts' => TRUE, 'defaultColumns' => 'severity,section,name,message'])
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
  }

  public function getBootOptions(): array {
    return ['default' => 'none', 'allow' => ['none']];
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $filterSeverities = $this->parseFilter($input);

    $showBootMsgsByDefault = in_array($input->getOption('out'), ['table', 'pretty']);
    $setup = $this->bootSetupSubsystem($input, $output, $showBootMsgsByDefault ? 0 : OutputInterface::VERBOSITY_VERBOSE);
    $reqs = $setup->checkRequirements();
    $messages = array_filter($reqs->getMessages(), function ($m) use ($filterSeverities) {
      return in_array($m['severity'], $filterSeverities);
    });
    $this->sendStandardTable($messages);
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
