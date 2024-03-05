<?php
namespace Civi\Cv;

use LesserEvil\ShellVerbosityIsEvil;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseApplication extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main(string $name, ?string $binDir, array $argv) {
    $class = static::class;

    Cv::plugins()->init();
    $application = Cv::filter("${name}.app.boot", [
      'app' => new $class($name, static::version() ?? 'UNKNOWN'),
    ])['app'];

    $input = new ArgvInput($argv);
    $output = new ConsoleOutput();

    $application->setAutoExit(FALSE);
    ErrorHandler::pushHandler();
    try {
      $result = $application->run($input, $output);
    }
    finally {
      ErrorHandler::popHandler();
    }
    exit($result);
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);

    $commands = Cv::filter($this->getName() . '.app.commands', [
      'commands' => $this->createCommands(),
    ])['commands'];
    $this->addCommands($commands);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultInputDefinition() {
    $definition = parent::getDefaultInputDefinition();
    $definition->addOption(new InputOption('cwd', NULL, InputOption::VALUE_REQUIRED, 'If specified, use the given directory as working directory.'));
    $definition->addOption(new InputOption('site-alias', NULL, InputOption::VALUE_REQUIRED, 'Load site connection data based on its alias'));
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
    Cv::filter($this->getName() . '.app.run', []);
    return parent::doRun($input, $output);
  }

  protected function configureIO(InputInterface $input, OutputInterface $output) {
    ShellVerbosityIsEvil::doWithoutEvil(function() use ($input, $output) {
      parent::configureIO($input, $output);
    });
  }

}
