<?php
namespace Civi\Cv;

use Civi\Cv\Util\AliasFilter;
use Civi\Cv\Util\CvArgvInput;
use LesserEvil\ShellVerbosityIsEvil;
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
    ErrorHandler::pushHandler();

    $preBootInput = new CvArgvInput($argv);
    $preBootOutput = new ConsoleOutput();
    Cv::ioStack()->push($preBootInput, $preBootOutput);

    try {
      $application = new static($name);
      $argv = AliasFilter::filter($argv);
      $result = $application->run(new CvArgvInput($argv), Cv::ioStack()->current('output'));
    }
    finally {
      Cv::ioStack()->pop();
    }

    ## NOTE: We do *not* use try/finally here. Doing so seems to counterintuitively
    ## muck with the exit code in some cases (eg `testPhpEval_ExitCodeError()`).
    ErrorHandler::popHandler();

    exit($result);
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);
    $this->setAutoExit(FALSE);

    Cv::plugins()->init(['appName' => $this->getName(), 'appVersion' => $this->getVersion()]);
    Cv::filter($this->getName() . '.app.boot', ['app' => $this]);

    $commands = Cv::filter($this->getName() . '.app.commands', [
      'commands' => $this->createCommands(),
    ])['commands'];
    $this->addCommands($commands);
  }

  /**
   * @return \Symfony\Component\Console\Command\Command[]
   */
  public function createCommands($context = 'default') {
    return [];
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

  public function run(?InputInterface $input = NULL, ?OutputInterface $output = NULL) {
    $input = $input ?: new CvArgvInput();
    $output = $output ?: new ConsoleOutput();

    try {
      Cv::ioStack()->push($input, $output);
      return parent::run($input, $output);
    }
    finally {
      Cv::ioStack()->pop();
    }
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

    if ($input->hasParameterOption('--site-alias')) {
      $aliasEvent = Cv::filter($this->getName() . ".app.site-alias", [
        'alias' => $input->getParameterOption('--site-alias'),
        'app' => $this,
        'input' => $input,
        'output' => $output,
        'argv' => $input->getOriginalArgv(),
        'transport' => NULL,
        'exec' => function() use (&$aliasEvent) {
          return parent::doRun($aliasEvent['input'], $aliasEvent['output']);
        },
      ]);
      if (empty($aliasEvent['transport'])) {
        throw new \RuntimeException("Unknown site alias: " . $aliasEvent['alias']);
      }
      return call_user_func($aliasEvent['transport'], $aliasEvent);
    }

    return parent::doRun($input, $output);
  }

  protected function configureIO(InputInterface $input, OutputInterface $output) {
    ShellVerbosityIsEvil::doWithoutEvil(function() use ($input, $output) {
      parent::configureIO($input, $output);
    });
  }

}
