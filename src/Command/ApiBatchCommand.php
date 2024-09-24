<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApiBatchCommand extends CvCommand {

  /**
   * @var array
   */
  public $defaults = [];

  public $metaDefaults = [
    'v3' => ['version' => 3],
    'v4' => ['version' => 4, 'checkPermissions' => FALSE],
  ];

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('api:batch')
      ->setDescription('Call multiple APIs via STDIN/STDOUT (bidirectional pipe; batch mode)')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (json)', 'json')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (json)', 'json')
      ->addOption('defaults', NULL, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Set default options for all requests (Either string \'v3\', \'v4\', or a JSON object \'{key:value...}\'', ['v3'])
      ->setHelp('Call multiple APIs via STDIN/STDOUT (bidirectional pipe; batch mode)

The api:batch command implements a line-oriented protocol for running CiviCRM APIs using pipes. In basic form, you may pipe in JSON:

Example: APIv3
  echo \'[["Contact","get",{"id":100}],["Contact","get",{"id":101}]]\' | cv api:batch

Example: APIv4
  echo \'[["Contact","get",{"where":[["id","=",100]]}]]\' | cv api:batch --defaults=v4

Example: Extra defaults
  echo \'[["Contact","get",{"where":[["id","=",100]]}]]\' | cv api:batch --defaults=\'{"version":4,"checkPermissions":true}\'

More advanced consumers may use bi-directional piping to send and receive multiple, dynamic calls - without requiring multiple bootstraps.

Protocol:

* Client may submit multiple lines of input (separated by \\n).
* Each line is executed synchronously. Caller should write one line and then read one line.
* Each line of input is a JSON document, listing a batch of API requests.
* Each line of output is a JSON document, listing a batch of API responses.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($input->getOption('in') !== 'json' || $input->getOption('out') !== 'json') {
      // Other formats may not work with the fgets() loop.
      throw new \Exception("api:batch only supports JSON dialog");
    }

    $addDefault = function($v) {
      $this->defaults = \CRM_Utils_Array::crmArrayMerge($v, $this->defaults);
    };
    foreach ((array) $input->getOption('defaults') as $dflExpr) {
      if (isset($this->metaDefaults[$dflExpr])) {
        $addDefault($this->metaDefaults[$dflExpr]);
      }
      elseif ($dflExpr[0] === '{') {
        if (($parsed = json_decode($dflExpr, 1)) !== NULL) {
          $addDefault($parsed);
        }
        else {
          throw new \Exception("Malformed defaults (JSON): $dflExpr");
        }
      }
      else {
        throw new \Exception("Malformed defaults: $dflExpr");
      }
    }

    if ($output->isVerbose()) {
      fwrite(STDERR, sprintf("Set API defaults: %s\n", json_encode($this->defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
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

    return 0;
  }

}
