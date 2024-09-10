<?php
namespace Civi\Cv;

use LesserEvil\ShellVerbosityIsEvil;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Component\Console\Application {

  protected $deprecatedAliases = [
    'debug:container' => 'service',
    'debug:event-dispatcher' => 'event',
  ];

  /**
   * Determine the version number.
   *
   * @return string|null
   *   Ex: '0.3.4'
   *   Ex: NULL (if it cannot be determined)
   */
  public static function version(): ?string {
    $marker = '@' . 'package' . '_' . 'version' . '@';
    $v = '@package_version@';
    if ($v !== $marker) {
      return $v;
    }
    if (is_callable('\Composer\InstalledVersions::getVersion')) {
      $v = \Composer\InstalledVersions::getVersion('civicrm/cv');
      if (preg_match('/^\d+\.\d+/', $v)) {
        return $v;
      }
    }
    return NULL;
  }

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir, array $argv) {
    $application = new Application('cv', static::version() ?? 'UNKNOWN');

    $input = new ArgvInput($argv);
    $output = new ConsoleOutput();

    $application->setAutoExit(FALSE);
    ErrorHandler::pushHandler();
    $result = $application->run($input, $output);
    ErrorHandler::popHandler();
    exit($result);
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);
    $this->addCommands($this->createCommands());
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultInputDefinition() {
    $definition = parent::getDefaultInputDefinition();
    $definition->addOption(new InputOption('cwd', NULL, InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'));
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(InputInterface $input, OutputInterface $output) {
    ErrorHandler::setRenderer(function($e) use ($output) {
      if ($output instanceof ConsoleOutputInterface) {
        $this->renderThrowable($e, $output->getErrorOutput());
      }
      else {
        $this->renderThrowable($e, $output);
      }
    });

    $workingDir = $input->getParameterOption(array('--cwd'));
    if (FALSE !== $workingDir && '' !== $workingDir) {
      if (!is_dir($workingDir)) {
        throw new \RuntimeException("Invalid working directory specified, $workingDir does not exist.");
      }
      if (!chdir($workingDir)) {
        throw new \RuntimeException("Failed to use directory specified, $workingDir as working directory.");
      }
    }
    return parent::doRun($input, $output);
  }

  /**
   * Construct command objects
   *
   * @return array of Symfony Command objects
   */
  public function createCommands($context = 'default') {
    $commands = array();
    $commands[] = new \Civi\Cv\Command\ApiCommand();
    $commands[] = new \Civi\Cv\Command\Api4Command();
    $commands[] = new \Civi\Cv\Command\ApiBatchCommand();
    $commands[] = new \Civi\Cv\Command\AngularModuleListCommand();
    $commands[] = new \Civi\Cv\Command\AngularHtmlListCommand();
    $commands[] = new \Civi\Cv\Command\AngularHtmlShowCommand();
    $commands[] = new \Civi\Cv\Command\DebugContainerCommand();
    $commands[] = new \Civi\Cv\Command\DebugDispatcherCommand();
    $commands[] = new \Civi\Cv\Command\ExtensionDownloadCommand();
    $commands[] = new \Civi\Cv\Command\ExtensionEnableCommand();
    $commands[] = new \Civi\Cv\Command\ExtensionDisableCommand();
    $commands[] = new \Civi\Cv\Command\ExtensionListCommand();
    $commands[] = new \Civi\Cv\Command\ExtensionUninstallCommand();
    $commands[] = new \Civi\Cv\Command\ExtensionUpgradeDbCommand();
    $commands[] = new \Civi\Cv\Command\FillCommand();
    $commands[] = new \Civi\Cv\Command\FlushCommand();
    $commands[] = new \Civi\Cv\Command\PathCommand();
    $commands[] = new \Civi\Cv\Command\PipeCommand();
    $commands[] = new \Civi\Cv\Command\SettingSetCommand();
    $commands[] = new \Civi\Cv\Command\SettingGetCommand();
    $commands[] = new \Civi\Cv\Command\SettingRevertCommand();
    $commands[] = new \Civi\Cv\Command\SqlCliCommand();
    $commands[] = new \Civi\Cv\Command\ShowCommand();
    // $commands[] = new \Civi\Cv\Command\UpgradeCommand();
    $commands[] = new \Civi\Cv\Command\UpgradeDbCommand();
    // $commands[] = new \Civi\Cv\Command\UpgradeDlCommand();
    // $commands[] = new \Civi\Cv\Command\UpgradeGetCommand(); // FIXME: Revalidate and add UpgradeGetCommandTest to the group "std".
    // $commands[] = new \Civi\Cv\Command\UpgradeReportCommand(); // FIXME: Revalidate and add UpgradeReportCommandTest to the group "std".
    $commands[] = new \Civi\Cv\Command\HttpCommand();
    $commands[] = new \Civi\Cv\Command\UrlCommand();
    if ($context !== 'repl') {
      $commands[] = new \Civi\Cv\Command\BootCommand();
      $commands[] = new \Civi\Cv\Command\CliCommand();
      $commands[] = new \Civi\Cv\Command\EvalCommand();
      $commands[] = new \Civi\Cv\Command\ScriptCommand();
      $commands[] = new \Civi\Cv\Command\CoreCheckReqCommand();
      $commands[] = new \Civi\Cv\Command\CoreInstallCommand();
      $commands[] = new \Civi\Cv\Command\CoreUninstallCommand();
      $commands[] = new \Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand();
    }
    return $commands;
  }

  protected function configureIO(InputInterface $input, OutputInterface $output) {
    ShellVerbosityIsEvil::doWithoutEvil(function() use ($input, $output) {
      parent::configureIO($input, $output);
    });
  }

  public function find($name) {
    if (isset($this->deprecatedAliases[$name])) {
      fprintf(STDERR, "WARNING: Subcommand \"%s\" has been renamed to \"%s\". In the future, the old name may stop working.\n\n", $name, $this->deprecatedAliases[$name]);
      $name = $this->deprecatedAliases[$name];
    }
    return parent::find($name);
  }

}
