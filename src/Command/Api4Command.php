<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\Api4ArgParser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Api4Command extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;

  /**
   * @var array
   */
  var $defaults;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    $this->defaults = array('version' => 4);
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('api4')
      ->setDescription('Call APIv4')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (args,json)', 'args')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getTabularFormats()) . ')', Encoder::getDefaultFormat())
      ->addArgument('Entity.action', InputArgument::REQUIRED)
      ->addArgument('key:json-value', InputArgument::IS_ARRAY)
      ->setHelp('Call APIv4

Usage:
  cv api4 ENTITY.ACTION
  cv api4 -- ENTITY.ACTION [+]KEY=VALUE...
  echo JSON | cv api4 ENTITY.ACTION --in=json

The most precise way to input information is to pipe JSON, but in day-to-day
usage it may be easier to asssign parameters on the command-line. Key pieces:

  +        Use the "add" mode (in which values are added to a sub-array)
  KEY      Set the value of KEY
  VALUE    The new value. This may be JSON (beginning with \'[\' or \'{\' or \'"\');
           otherwise, it is treated as a string-literal.

Examples:
  cv api4 system.get
  cv api4 -- contact.get +select=display_name \'+where=["id",">",123]\' limit=10
  cv api4 -- contact.get \'select=["display_name"]\' \'where=[["id","=",123]]\'
  echo \'{"id":10, "api.Email.get": 1}\' | cv api4 contact.get --in=json

NOTE: To change the default output format, set CV_OUTPUT.
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    if (!function_exists('civicrm_api4')) {
      throw new \RuntimeException("Please enable APIv4 before running APIv4 commands.");
    }

    list($entity, $action) = explode('.', $input->getArgument('Entity.action'));
    $params = $this->parseParams($input);
    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
      $output->writeln("<info>Entity</info>: $entity");
      $output->writeln("<info>Action</info>: $action");
      $output->writeln("<info>Params</info>: " . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    $result = \civicrm_api4($entity, $action, $params);

    $out = $input->getOption('out');
    if (!in_array($out, Encoder::getFormats()) && in_array($out, Encoder::getTabularFormats())) {
      // For tabular output, we have to be picky about what data to display.
      if ($action !== 'get' || !$result) {
        $output->getErrorOutput()
          ->writeln("<error>The output format \"$out\" only works with tabular data. Try using a \"get\" API. Forcing format to \"json-pretty\".</error>");
        $input->setOption('out', 'json-pretty');
        $this->sendResult($input, $output, $result);
      }
      else {
        $columns = empty($params['select']) ? array_keys($result->first()) : explode(',', $params['select']);
        $this->sendTable($input, $output, (array) $result, $columns);
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
    $args = $input->getArgument('key:json-value');
    switch ($input->getOption('in')) {
      case 'args':
        $p = new Api4ArgParser();
        $params = $p->parse($args, $this->defaults);
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
