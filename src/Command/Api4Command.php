<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\Util\Api4ArgParser;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Api4Command extends CvCommand {

  use StructuredOutputTrait;

  /**
   * @var array
   */
  public $defaults;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    $this->defaults = array('version' => 4, 'checkPermissions' => FALSE);
    parent::__construct($name);
  }

  protected function configure() {
    $C = '<comment>';
    $_C = '</comment>';
    $I = '<info>';
    $_I = '</info>';

    $this
      ->setName('api4')
      ->setDescription('Call APIv4')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (args,json)', 'args')
      ->configureOutputOptions(['tabular' => TRUE, 'shortcuts' => ['table', 'list', 'json']])
      ->addOption('dry-run', 'N', InputOption::VALUE_NONE, 'Preview the API call. Do not execute.')
      ->addArgument('Entity.action', InputArgument::REQUIRED)
      ->addArgument('key=value', InputArgument::IS_ARRAY)
      ->setHelp("Call an entity/action in APIv4

If your inputs are determined dynamically (e.g. from an external or untrusted
data-source), then it is better to pass parameters via pipe using strict JSON format.
This minimizes the risk that a dynamic value will be incorrectly escaped.

Alas, strict JSON is cumbersome to manually enter on the CLI.

If your inputs are entered manually, then it is easier to use a mix of \"+Options\"
and \"JSON-ish Key-Value\". The \"+Options\" are ideal for common parameters
(like \"select\" or \"where\"), and \"JSON-ish Key-Value\" is a decent fallback
for less common parameters.

Below, we consider a specification for each format and a set of examples.

If you'd like to inspect the behavior more carefully, try using {$I}--dry-run{$_I} ({$I}-N{$_I}).

{$C}Specification: Piped JSON{$_C}

    {$C}echo{$_C} {$I}JSON{$_I} | {$C}cv api4{$_C} {$I}ENTITY{$_I}.{$I}ACTION{$_I} {$C}--in=json{$_C}

{$C}Specification: +Options{$_C}

    {$C}cv api4{$_C} {$I}ENTITY{$_I}.{$I}ACTION{$_I} [{$C}+{$_C}{$I}OP{$_I}{$C} {$_C}{$I}EXPR{$_I}]...

    Each \"+Option\" allows you to specify a common APIv4 parameter using a
    pithy, purpose-built notation. For example:

    Option         Examples
    {$C}+s{$_C}|{$C}+select{$_C}     +select id,display_name
                   +select=id,display_name
                   +s id,display_name
    {$C}+w{$_C}|{$C}+where{$_C}      +where 'first_name like \"Adams%\"'
                   +w 'first_name like \"Adams%\"'
    {$C}+o{$_C}|{$C}+orderBy{$_C}    +orderBy last_name,first_name
                   +o last_name,first_name
                   +o 'last_name DESC,first_name ASC'
    {$C}+l{$_C}|{$C}+limit{$_C}      +limit 15@60
                   +l 15
    {$C}+v{$_C}|{$C}+value{$_C}      +v name=Alice
                   +v name=Alice

    NOTE: The +{$I}OP{$_I} may be written long ({$C}+where{$_C}) or short ({$C}+w{$_C}). It is
    valid to separate the +{$I}OP{$_I} and {$I}EXPR{$_I} using a space, colon, or equals sign.

{$C}Specification: JSON-ish Key-Value{$_C}

    {$C}cv api4{$_C} {$I}ENTITY{$_I}.{$I}ACTION{$_I} [{$I}KEY{$_I}={$I}VALUE{$_I}]... [{$I}JSON-OBJECT{$_I}]...

    Use {$I}KEY{$_I}={$I}VALUE{$_I} to set an input to a specific value. The value may be a bare string
    or it may be JSON (beginning with '[' or '{' or '\"').
    
    For parameters which expect boolean values, use {$I}1{$_I} for true and {$I}0{$_I} for false.

    Use {$I}JSON-OBJECT{$_I} if you want to pass several fields as one pure JSON string.
    A parameter which begins with '{' will be interpreted as a JSON expression.

{$C}Example: Get all contacts{$_C}
    cv api4 Contact.get

{$C}Example: Get all email addresses for contact 33, bypassing the permissions check{$_C}
    cv api4 Email.get +w 'contact_id = 33' checkPermissions=0

{$C}Example: Get ten contacts{$_C} (All examples are equivalent.)
    cv api4 Contact.get +s id,display_name +l 10
    cv api4 Contact.get select='[\"id\",\"display_name\"]' limit=10
    cv api4 Contact.get '{\"select\":[\"id\",\"display_name\"],\"limit\":10}'
    echo '{\"select\":[\"id\",\"display_name\"],\"limit\":10}' | cv api4 Contact.get --in=json

{$C}Example: Find ten contacts named \"Adam\"{$_C}
    cv api4 Contact.get +s display_name +w 'display_name LIKE \"Adam%\"' limit=10

{$C}Example: Find contact names for IDs between 100 and 200, ordered by last name{$_C}
    cv api4 Contact.get +s display_name +o last_name +w 'id >= 100' +w 'id <= 200'

{$C}Example: Change do_not_phone for everyone named Adam{$_C}
    cv api4 Contact.update +w 'display_name like %Adam%' +v do_not_phone=1

NOTE: To change the default output format, set CV_OUTPUT.
");
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $C = '<comment>';
    $_C = '</comment>';
    $I = '<info>';
    $_I = '</info>';

    if (!function_exists('civicrm_api4')) {
      throw new \RuntimeException("Please enable APIv4 before running APIv4 commands.");
    }

    list($entity, $action) = explode('.', $input->getArgument('Entity.action'));
    $params = $this->parseParams($input);
    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE || $input->getOption('dry-run')) {
      $output->writeln("{$I}Entity{$_I}: {$C}$entity{$_C}");
      $output->writeln("{$I}Action{$_I}: {$C}$action{$_C}");
      $output->writeln("{$I}Params{$_I}: " . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    if ($input->getOption('dry-run')) {
      return 0;
    }
    $result = \civicrm_api4($entity, $action, $params);

    $out = $input->getOption('out');
    if (!in_array($out, Encoder::getFormats()) && in_array($out, Encoder::getTabularFormats())) {
      if (!empty($params['select'])) {
        $columns = $params['select'];
      }
      else {
        $columns = count($result) ? array_keys($result->first()) : [''];
      }
      $this->sendTable($input, $output, (array) $result, $columns);
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
