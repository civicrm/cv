<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApiCommand extends CvCommand {

  use StructuredOutputTrait;

  /**
   * @var array
   */
  public $defaults;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    $this->defaults = array('version' => 3);
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('api3')
      ->setAliases(['api'])
      ->setDescription('Call APIv3')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (args,json)', 'args')
      ->configureOutputOptions(['tabular' => TRUE, 'shortcuts' => ['table', 'list', 'json']])
      ->addArgument('Entity.action', InputArgument::REQUIRED)
      ->addArgument('key=value', InputArgument::IS_ARRAY)
      ->setHelp('Call APIv3

Examples:
  cv api system.get
  cv api contact.get id=10
  echo \'{"id":10, "api.Email.get": 1}\' | cv api contact.get --in=json

TIP: To change the default output format, set CV_OUTPUT.

TIP: To display a full backtrace of any errors, pass "-vv" (very verbose).
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $C = '<comment>';
    $_C = '</comment>';
    $I = '<info>';
    $_I = '</info>';

    list($entity, $action) = explode('.', $input->getArgument('Entity.action'));
    $params = $this->parseParams($input);

    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE && !array_key_exists('debug', $params)) {
      $params['debug'] = 1;
    }

    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
      $output->writeln("{$I}Entity{$_I}: {$C}$entity{$_C}");
      $output->writeln("{$I}Action{$_I}: {$C}$action{$_C}");
      $output->writeln("{$I}Params{$_I}: " . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    $result = \civicrm_api($entity, $action, $params);

    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE && $result['trace']) {
      $output->getErrorOutput()->writeln("<error>Error: " . (isset($result['error_message']) ? $result['error_message'] : "") . "</error>");
      $output->getErrorOutput()->writeln("  " . str_replace("\n", "\n  ", $result['trace']), OutputInterface::OUTPUT_RAW);
      $output->getErrorOutput()->write("\n");
      unset($result['trace']);
    }

    $out = $input->getOption('out');
    if (!in_array($out, Encoder::getFormats()) && in_array($out, Encoder::getTabularFormats())) {
      // For tabular output, we have to be picky about what data to display.
      if ($action !== 'get' || !isset($result['values']) || !empty($result['is_error'])) {
        $output->getErrorOutput()
          ->writeln("<error>The output format \"$out\" only works with tabular data. Try using a \"get\" API. Forcing format to \"json-pretty\".</error>");
        $input->setOption('out', 'json-pretty');
        $this->sendResult($input, $output, $result);
      }
      else {
        $columns = empty($params['return']) ? NULL : explode(',',
          $params['return']);
        $this->sendTable($input, $output, array_values($result['values']),
          $columns);
      }
    }
    else {
      $this->sendResult($input, $output, $result);
    }

    return empty($result['is_error']) ? 0 : 1;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param $matches
   * @return array
   */
  protected function parseParams(InputInterface $input) {
    $args = $input->getArgument('key=value');
    switch ($input->getOption('in')) {
      case 'args':
        $params = $this->defaults;
        foreach ($args as $arg) {
          preg_match('/^([^=]+)=(.*)$/', $arg, $matches);
          $params[$matches[1]] = $matches[2];
        }
        break;

      case 'json':
        $json = stream_get_contents(STDIN);
        if (empty($json)) {
          $params = $this->defaults;
        }
        else {
          $params = array_merge($this->defaults, json_decode($json, TRUE));
        }
        break;

      default:
        throw new \RuntimeException('Unknown input format');
    }
    return $params;
  }

}
