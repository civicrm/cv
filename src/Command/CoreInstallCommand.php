<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\Util\OptionalOption;
use Civi\Cv\Util\SetupCommandTrait;
use Civi\Cv\Util\DebugDispatcherTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class CoreInstallCommand extends CvCommand {

  use SetupCommandTrait;
  use DebugDispatcherTrait;

  protected function configure() {
    $this
      ->setName('core:install')
      ->setDescription('Initialize the CiviCRM data-files and database-schema')
      ->configureSetupOptions()
      ->addOption('abort', 'A', InputOption::VALUE_NONE, 'In the event of conflict, abort.')
      ->addOption('keep', 'K', InputOption::VALUE_NONE, 'In the event of conflict, keep existing files/tables.')
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'In the event of conflict, overwrite existing files/tables.')
      ->addOption('debug-event', NULL, InputOption::VALUE_OPTIONAL, 'Display debug information about events and exit. Give an event name or regex.')
      ->addOption('debug-model', NULL, InputOption::VALUE_NONE, 'Display debug information about model and exit.')
      ->setHelp('
Initialize the CiviCRM data-files and database-schema

Example: Install on a basic WordPress build.
$ cv core:install
$ wp plugin activate civicrm

Example: Install on a basic Drupal 7 build.
$ cv core:install --url=https://example.com/
$ drush -y en civicrm

Example: Install on WordPress with a custom language and database.
$ cv core:install --lang=fr_FR --db=mysql://user:pass@host:3306/database
$ wp plugin activate civicrm

Example: Forcibly reinstall/overwrite. Display verbose debug info.
$ cv core:install -f -vv

Example: Inspect the installer events+plugins
$ cv core:install --debug-event

Example: Install while setting a hidden option
$ cv core:install --model=extras.opt-in.versionCheck=1
$ cv core:install -m extras.opt-in.versionCheck=1
');
  }

  public function getBootOptions(): array {
    return ['default' => 'none', 'allow' => ['none']];
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $setup = $this->bootSetupSubsystem($input, $output);

    $debugMode = FALSE;

    $debugEvent = OptionalOption::parse($input, ['--debug-event'], NULL, '');
    if ($debugEvent !== NULL) {
      $eventNames = $this->findEventNames($setup->getDispatcher(), $debugEvent);
      $this->printEventListeners($output, $setup->getDispatcher(), $eventNames);
      $debugMode = TRUE;
    }

    if ($input->getOption('debug-model')) {
      $output->writeln(Encoder::encode($setup->getModel()->getValues(), 'json-pretty'));
      $debugMode = 1;
    }

    if ($debugMode) {
      return 0;
    }

    $this->runSetup($input, $output, $setup);
    if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
      $output->writeln(Encoder::encode($setup->getModel()->getValues(), 'json-pretty'));
    }
    return 0;
  }

  /**
   * Determine what action to take to resolve a conflict.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $title
   *   The thing which had a conflict.
   * @return string
   *   Ex: 'abort', 'keep', 'overwrite'.
   */
  protected function pickConflictAction(
    InputInterface $input,
    OutputInterface $output,
    $title
  ) {
    if ($input->getOption('abort')) {
      return 'abort';
    }
    if ($input->getOption('keep')) {
      return 'keep';
    }
    if ($input->getOption('force')) {
      return 'overwrite';
    }

    $helper = $this->getHelper('question');
    $question = new ChoiceQuestion(
      "The $title already exists. What you like to do?",
      array(
        'a' => "Abort. (Default.) (Equivalent to -A.)",
        'k' => "Keep existing $title. (WARNING: This may fail if the existing version is out-of-date.) (Equivalent to -K.)",
        'o' => "Overwrite with new $title. (WARNING: This may destroy data.) (Equivalent to -f.)",
      ),
      'a'
    );
    switch ($helper->ask($input, $output, $question)) {
      case 'k':
        return 'keep';

      case 'o':
        return 'overwrite';

      case 'a':
      default:
        return 'abort';
    }
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $setup
   * @throws \Exception
   */
  protected function runSetup(InputInterface $input, OutputInterface $output, $setup) {
    // Validate system requirements
    $reqs = $setup->checkRequirements();
    foreach ($reqs->getWarnings() as $msg) {
      $output->writeln(sprintf("<comment>WARNING: (<info>%s</info>) %s:</comment> %s", $msg['section'], $msg['name'], $msg['message']));
    }
    $errors = $reqs->getErrors();
    if ($errors) {
      foreach ($errors as $msg) {
        $output->writeln(sprintf("<error>ERROR: (%s) %s:</error> %s", $msg['section'], $msg['name'], $msg['message']));
      }
      throw new \Exception('Requirements check failed.');
    }

    // Install!
    $installed = $setup->checkInstalled();
    if (!$installed->isSettingInstalled()) {
      $output->writeln(sprintf("<info>Creating file <comment>%s</comment></info>", $setup->getModel()->settingsPath));
      $setup->installFiles();
    }
    else {
      $output->writeln(sprintf("<info>Found existing <comment>%s</comment> in <comment>%s</comment></info>", basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
      switch ($this->pickConflictAction($input, $output, 'civicrm.settings.php')) {
        case 'abort':
          throw new \Exception("Aborted");

        case 'overwrite':
          $output->writeln(sprintf("<info>Removing <comment>%s</comment> from <comment>%s</comment></info>", basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
          $setup->uninstallFiles();
          $output->writeln(sprintf("<info>Creating <comment>%s</comment> in <comment>%s</comment></info>", basename($setup->getModel()->settingsPath), dirname($setup->getModel()->settingsPath)));
          $setup->installFiles();
          break;

        case 'keep':
          break;

        default:
          throw new \Exception("Unrecognized action");
      }
    }

    if (!$installed->isDatabaseInstalled()) {
      $output->writeln(sprintf("<info>Creating <comment>civicrm_*</comment> database tables in <comment>%s</comment></info>", $setup->getModel()->db['database']));
      $setup->installDatabase();
    }
    else {
      $output->writeln(sprintf("<info>Found existing <comment>civicrm_*</comment> database tables in <comment>%s</comment></info>", $setup->getModel()->db['database']));
      switch ($this->pickConflictAction($input, $output, 'database tables')) {
        case 'abort':
          throw new \Exception("Aborted");

        case 'overwrite':
          $output->writeln(sprintf("<info>Removing <comment>civicrm_*</comment> database tables in <comment>%s</comment></info>", $setup->getModel()->db['database']));
          $setup->uninstallDatabase();
          $output->writeln(sprintf("<info>Creating <comment>civicrm_*</comment> database tables in <comment>%s</comment></info>", $setup->getModel()->db['database']));
          $setup->installDatabase();
          break;

        case 'keep':
          break;

        default:
          throw new \Exception("Unrecognized action");
      }
    }
  }

}
