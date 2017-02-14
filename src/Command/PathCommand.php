<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\ExtensionUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class PathCommand extends BaseExtensionCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('path')
      ->setAliases(array())
      ->setDescription('Look up the path to a file or directory')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getTabularFormats()) . ')', Encoder::getDefaultFormat('list'))
      ->addOption('columns', NULL, InputOption::VALUE_REQUIRED, 'List of columns to display (comma separated; type, name, value)')
      ->addOption('ext', 'x', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'An extension name. Identify the extension by full key ("org.example.foobar") or short name ("foobar")')
      ->addOption('config', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A config property. (Ex: customFileUploadDir, customPHPPathDir, customTemplateDir, extensionsDir, imageUploadDir, templateCompileDir, uploadDir)')
      ->addOption('dynamic', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A dynamic path expression (Ex: "[civicrm.root]/packages")')
      ->addArgument('file', InputArgument::IS_ARRAY, 'Optionally specify files')
      ->setHelp('Look up the path to a file or directory

Examples (directories):
  cv path -x cividiscount
  cv path -x cividiscount -x styleguide -x flexmailer
  cv path -c templateCompileDir
  cv path -d \'[civicrm.root]/packages\'

Examples (files):
  cv path -x cividiscount -x styleguide -x flexmailer info.xml
  cv path -c uploadDir hello.jpg
  cv path -d \'[civicrm.root]\' packages/DB.php
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    $pathResults = array();
    $returnValue = 0;

    $mapper = \CRM_Extension_System::singleton()->getMapper();
    foreach ($input->getOption('ext') as $keyOrName) {
      if (strpos($keyOrName, '.') === FALSE) {
        $shortMap = $this->getShortMap();
        if (isset($shortMap[$keyOrName]) && count($shortMap[$keyOrName]) === 1) {
          $keyOrName = $shortMap[$keyOrName][0];
        }
      }

      try {
        $pathResults[] = array(
          'type' => 'ext',
          'name' => $keyOrName,
          'value' => \CRM_Utils_File::addTrailingSlash($mapper->keyToBasePath($keyOrName)),
        );
      }
      catch (\CRM_Extension_Exception_MissingException $e) {
        $output->getErrorOutput()->writeln("<error>Ignoring unrecognized extension \"$keyOrName\"</error>");
        $returnValue = 1;
      }
    }

    foreach ($input->getOption('config') as $configProperty) {
      $pathResults[] = array(
        'type' => 'config',
        'name' => $configProperty,
        'value' => \CRM_Core_Config::singleton()->{$configProperty},
      );
    }

    foreach ($input->getOption('dynamic') as $dynExpr) {
      if (!is_callable(array('Civi', 'paths'))) {
        $output->getErrorOutput()->writeln("<error>Dynamic path expressions are only available on CiviCRM v4.7+</error>");
        $returnValue = 1;
        break;
      }

      $fullExpr = preg_match(';^\[[^\]]+\]$;', $dynExpr) ? "$dynExpr/." : $dynExpr;

      $pathResults[] = array(
        'type' => 'dynamic',
        'name' => $dynExpr,
        'value' => \CRM_Utils_File::addTrailingSlash(\Civi::paths()->getPath($fullExpr)),
      );
    }

    if (empty($pathResults)) {
      $output->getErrorOutput()->writeln("<error>No paths found. Must specify -x, -s, or -d. (See also: cv path -h)</error>");
      return 1;
    }

    $columns = $this->parseColumns($input, array(
      'list' => array('value'),
    ));

    if (!$input->getArgument('file')) {
      $this->sendTable($input, $output, $pathResults, $columns);
      return $returnValue;
    }
    else {
      $fileResults = array();
      foreach ($pathResults as $pathResult) {
        foreach ($input->getArgument('file') as $file) {
          $fileResult = $pathResult;
          $fileResult['value'] .= $file;
          $fileResults[] = $fileResult;
        }
      }
      $this->sendTable($input, $output, $fileResults, $columns);
      return $returnValue;
    }
  }

  /**
   * Determine the columns to display.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param array $defaultColumns
   *   Ex: $defaultColumns['table'] = array('name', 'value').
   * @return array
   *   Ex: array('*') or array('value').
   */
  protected function parseColumns(InputInterface $input, $defaultColumns = array()) {
    $out = $input->getOption('out');
    if ($input->getOption('columns')) {
      return explode(',', $input->getOption('columns'));
    }
    elseif (isset($defaultColumns[$out])) {
      return $defaultColumns[$out];
    }
    else {
      return array('*');
    }
  }

}
