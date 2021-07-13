<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\BootTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApiBatchCommand extends BaseCommand {

  use BootTrait;

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
      ->setName('api:batch')
      ->setDescription('Call an API (batch mode)')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (json)', 'json')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (json)', 'json')
      ->addOption('defaults', NULL, InputOption::VALUE_REQUIRED, 'Set default options for all requests (JSON-formatted)', '')
      ->setHelp('Call a series of APIs

Example: APIv3 with two distinct calls
  echo \'[["Contact","get",{"id":100}],["Contact","get",{"id":101}]]\' | cv api:batch

Example: APIv4 with one call
  echo \'[["Contact","get",{"where":[["id","=",100]]}]]\' | cv api:batch --default=\'{"version":4,"checkPermissions":false}\'

Each line of input is decoded as a JSON document. The JSON document is an array
of API calls.

Each line of output is encoded as a JSON document. The JSON document is an array
of API results.
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($input->getOption('in') !== 'json' || $input->getOption('out') !== 'json') {
      // Other formats may not work with the fgets() loop.
      throw new \Exception("api:batch only supports JSON dialog");
    }
    $this->boot($input, $output);

    if (!empty($input->getOption('defaults'))) {
      $newDefaults = json_decode($input->getOption('defaults'), 1);
      $this->defaults = \CRM_Utils_Array::crmArrayMerge($newDefaults, $this->defaults);
      if ($output->isVerbose()) {
        fwrite(STDERR, sprintf("Set API defaults: %s\n", json_encode($this->defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
      }
    }

    $lineNum = 0;

    // stream_set_blocking(STDIN, 0);
    while (FALSE !== ($line = fgets(STDIN))) {
      $lineNum++;
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      $todos = json_decode($line, TRUE);
      $result = array();
      if ($todos === NULL) {
        fwrite(STDERR, sprintf("JSON cannot be decoded (line $lineNum)"));
      }
      else {
        foreach ($todos as $k => $api) {
          if (!is_array($api) || !isset($api[1]) || !is_string($api[0]) || !is_string($api[1])) {
            fwrite(STDERR, "JSON data is structured incorrectly (line $lineNum)\n");
            $result[$k] = array('is_error' => 1, 'error_message' => "JSON data is structured incorrectly (line $lineNum)");
            continue;
          }
          list ($entity, $action, $params) = $api;
          $params = \CRM_Utils_Array::crmArrayMerge($params, $this->defaults);
          if ($output->isVerbose()) {
            fwrite(STDERR, sprintf("Execute API calls: %s\n", json_encode([$entity, $action, $params], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
          }
          $result[$k] = \civicrm_api($entity, $action, $params);
        }
      }
      echo json_encode($result);
      echo "\n";

      if (ob_get_level() > 0) {
        // Paranoia: don't know if booting CMS's has impact on buffering.
        ob_flush();
      }
      flush();
    }
  }

}
