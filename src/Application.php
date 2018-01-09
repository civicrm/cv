<?php
namespace Civi\Cv;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir) {
    $application = new Application('cv', '@package_version@');
    $application->run();
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);
    $this->addCommands($this->createCommands());
  }

  /**
   * Construct command objects
   *
   * @return array of Symfony Command objects
   */
  public function createCommands($context = 'default') {
    $commands = array();
    $commands[] = new \Civi\Cv\Command\ApiCommand();
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
    $commands[] = new \Civi\Cv\Command\ShowCommand();
    $commands[] = new \Civi\Cv\Command\UrlCommand();
    if ($context !== 'repl') {
      $commands[] = new \Civi\Cv\Command\BootCommand();
      $commands[] = new \Civi\Cv\Command\CliCommand();
      $commands[] = new \Civi\Cv\Command\EvalCommand();
      $commands[] = new \Civi\Cv\Command\ScriptCommand();
      $commands[] = new \Civi\Cv\Command\CoreInstallCommand();
    }
    return $commands;
  }

}
