<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ApiCommand extends BaseCommand {

  /**
   * @var array
   */
  var $defaults;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    $this->defaults = array('version' => 3);
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('api')
      ->setDescription('Call an API')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (args,json)', 'args')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat())
      ->addArgument('Entity.action', InputArgument::REQUIRED)
      ->addArgument('key=value', InputArgument::IS_ARRAY)
      ->setHelp('Call an API

Examples:
  cv api system.get
  cv api contact.get id=10
  echo \'{"id":10, "api.Email.get": 1}\' | cv api contact.get --in=json

NOTE: To change the default output format, set CV_OUTPUT.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    list($entity, $action) = explode('.', $input->getArgument('Entity.action'));
    $params = $this->parseParams($input);

    // Drush does the following...?
    //    global $user;
    //    CRM_Core_BAO_UFMatch::synchronize($user, FALSE, 'Drupal',
    //      civicrm_get_ctype('Individual')
    //    );

    $result = \civicrm_api($entity, $action, $params);
    $this->sendResult($input, $output, $result);
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
