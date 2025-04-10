<?php
namespace Civi\Cv;

use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication {

  const DEV_VERSION = '0.3.x';

  protected $deprecatedAliases = [
    'debug:container' => 'service',
    'debug:event-dispatcher' => 'event',
  ];

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    if ($version === 'UNKNOWN') {
      $version = static::version() ?? 'UNKNOWN';
    }
    parent::__construct($name, $version);
  }

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
      return ltrim($v, 'v');
    }
    if (is_callable('\Composer\InstalledVersions::getVersion')) {
      $v = \Composer\InstalledVersions::getVersion('civicrm/cv');
      if (preg_match('/^\d+\.\d+/', $v)) {
        return $v;
      }
    }
    return static::DEV_VERSION;
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
    $commands[] = new \Civi\Cv\Command\StatusCommand();
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
      $commands[] = new \Civi\Cv\Command\QueueNextCommand();
      $commands[] = new \Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand();
    }
    return $commands;
  }

  public function find($name) {
    if (isset($this->deprecatedAliases[$name])) {
      fprintf(STDERR, "WARNING: Subcommand \"%s\" has been renamed to \"%s\". In the future, the old name may stop working.\n\n", $name, $this->deprecatedAliases[$name]);
      $name = $this->deprecatedAliases[$name];
    }
    return parent::find($name);
  }

  public function doRenderThrowable(\Throwable $e, OutputInterface $output): void {
    parent::doRenderThrowable($e, $output);

    $indent = $output->isVerbose() ? ' ' : '  '; /* Because, yeah, sure. */

    $causes = [];
    $ei = $e;
    while ($ei !== NULL) {
      if (method_exists($ei, 'getCause')) {
        $causes[] = $ei->getCause();
      }
      $ei = $ei->getPrevious();
    }

    foreach ($causes as $cause) {
      if ($cause instanceof \DB_Error && !empty($cause->getDebugInfo())) {
        $output->writeln(sprintf("<comment>Debug Info</comment>:"));
        $parts = explode("\n", $cause->getDebugInfo());
        foreach ($parts as $part) {
          $output->writeln($indent . $part, OutputInterface::OUTPUT_RAW);
        }
        $output->writeln('');
        break;
      }
    }
  }

}
