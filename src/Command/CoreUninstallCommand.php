<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CoreUninstallCommand extends BaseCommand {

  use \Civi\Cv\Util\SetupCommandTrait;
  use \Civi\Cv\Util\DebugDispatcherTrait;

  protected function configure() {
    $this
      ->setName('core:uninstall')
      ->setDescription('Purge CiviCRM schema and settings files')
      ->configureSetupOptions()
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Remove without any prompt or confirmation')
      ->addOption('out', NULL, InputArgument::OPTIONAL, 'Specify return format (auto,' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat())
      ->setHelp('
Purge CiviCRM schema and settings files

TIP: If you have a special system configuration, it may help to pass the same
options for "core:uninstall" as the preceding "core:install".
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $setup = $this->bootSetupSubsystem($input, $output);

    $debugEvent = $this->parseOptionalOption($input, ['--debug-event'], NULL, '');
    if ($debugEvent !== NULL) {
      $eventNames = $this->findEventNames($setup->getDispatcher(), $debugEvent);
      $this->printEventListeners($output, $setup->getDispatcher(), $eventNames);
      return 0;
    }

    $installed = $setup->checkInstalled();
    if (!$installed->isDatabaseInstalled() && !$installed->isSettingInstalled()) {
      $output->writeln("<comment>CiviCRM does not appear to be installed.</comment>");
      return 0;
    }

    if (!$input->getOption('force')) {
      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion('<comment>Are you sure want to purge CiviCRM schema and settings? Data may be permanently destroyed.</comment> (y/N) ', FALSE);
      if (!$helper->ask($input, $output, $question)) {
        return 1;
      }
    }

    if ($installed->isDatabaseInstalled()) {
      $output->writeln(sprintf("<info>Removing <comment>civicrm_*</comment> database tables in <comment>%s</comment>.</info>", $setup->getModel()->db['database']));
      $setup->uninstallDatabase();
    }

    if ($installed->isSettingInstalled()) {
      $output->writeln(sprintf("<info>Removing <comment>%s</comment> from <comment>%s</comment>.</info>", basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
      $setup->uninstallFiles();
    }
  }

}
