<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\OptionalOption;
use Civi\Cv\Util\SetupCommandTrait;
use Civi\Cv\Util\DebugDispatcherTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CoreUninstallCommand extends CvCommand {

  use SetupCommandTrait;
  use DebugDispatcherTrait;
  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('core:uninstall')
      ->setDescription('Purge CiviCRM schema and settings files')
      ->configureSetupOptions()
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'Remove without any prompt or confirmation')
      ->configureOutputOptions()
      ->setHelp('
Purge CiviCRM schema and settings files

TIP: If you have a special system configuration, it may help to pass the same
options for "core:uninstall" as the preceding "core:install".
');
  }

  public function getBootOptions(): array {
    return ['default' => 'none', 'allow' => ['none']];
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $setup = $this->bootSetupSubsystem($input, $output);

    $debugEvent = OptionalOption::parse($input, ['--debug-event'], NULL, '');
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

    if ($installed->isDatabaseInstalled()) {
      $output->writeln(sprintf("<info>Found <comment>civicrm_*</comment> database tables in <comment>%s</comment></info>", $setup->getModel()->db['database']));
    }

    if ($installed->isSettingInstalled()) {
      $output->writeln(sprintf("<info>Found <comment>%s</comment> in <comment>%s</comment></info>", basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
    }

    if (!$input->getOption('force')) {
      $output->writeln('');
      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion('<comment>Are you sure want to purge the CiviCRM database and data files? Data may be permanently destroyed.</comment> (y/N) ', FALSE);
      if (!$helper->ask($input, $output, $question)) {
        $output->writeln("<comment>Aborted</comment>");
        return 1;
      }
    }

    if ($installed->isDatabaseInstalled()) {
      $output->writeln(sprintf("<info>Removing <comment>civicrm_*</comment> database tables in <comment>%s</comment></info>", $setup->getModel()->db['database']));
      $setup->uninstallDatabase();
    }

    if ($installed->isSettingInstalled()) {
      $output->writeln(sprintf("<info>Removing <comment>%s</comment> from <comment>%s</comment></info>", basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
      $setup->uninstallFiles();
    }

    return 0;
  }

}
