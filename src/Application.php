<?php
namespace Civi\Cv;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Debug\ErrorHandler;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir) {
    $application = new Application('cv', '@package_version@');

    $application->setAutoExit(FALSE);
    $running = TRUE;
    register_shutdown_function(function () use (&$running) {
      if ($running) {
        // Something - like a bad eval() - interrupted normal execution.
        // Make sure the status code reflects that.
        exit(255);
      }
    });
    $result = $application->run();
    $running = FALSE;
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
    $commands[] = new \Civi\Cv\Command\SqlCliCommand();
    $commands[] = new \Civi\Cv\Command\ShowCommand();
    // $commands[] = new \Civi\Cv\Command\UpgradeCommand();
    $commands[] = new \Civi\Cv\Command\UpgradeDbCommand();
    // $commands[] = new \Civi\Cv\Command\UpgradeDlCommand();
    // $commands[] = new \Civi\Cv\Command\UpgradeGetCommand(); // FIXME: Revalidate and add UpgradeGetCommandTest to the group "std".
    // $commands[] = new \Civi\Cv\Command\UpgradeReportCommand(); // FIXME: Revalidate and add UpgradeReportCommandTest to the group "std".
    $commands[] = new \Civi\Cv\Command\UrlCommand();
    if ($context !== 'repl') {
      $commands[] = new \Civi\Cv\Command\BootCommand();
      $commands[] = new \Civi\Cv\Command\CliCommand();
      $commands[] = new \Civi\Cv\Command\EvalCommand();
      $commands[] = new \Civi\Cv\Command\ScriptCommand();
      $commands[] = new \Civi\Cv\Command\CoreCheckReqCommand();
      $commands[] = new \Civi\Cv\Command\CoreInstallCommand();
      $commands[] = new \Civi\Cv\Command\CoreUninstallCommand();
    }
    return $commands;
  }

}
