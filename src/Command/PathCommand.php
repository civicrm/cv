<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\ExtensionTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PathCommand extends CvCommand {

  use ExtensionTrait;
  use StructuredOutputTrait;

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
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'list', 'availColumns' => 'type,expr,value', 'shortcuts' => ['table', 'list']])
      ->addOption('ext', 'x', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'An extension name. Identify the extension by full key ("org.example.foobar") or short name ("foobar"). Use "." for the default extension dir.')
      ->addOption('config', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A config property. (Ex: "templateCompileDir/en_US")')
      ->addOption('dynamic', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A dynamic path expression (v4.7+) (Ex: "[civicrm.root]/packages")')
      ->addOption('mkdir', 'm', InputOption::VALUE_NONE, 'Make a directory for the given path(s)')
      ->setHelp('Look up the path to a file or directory within CiviCRM.

Examples: Lookup extension paths
  cv path -x org.civicrm.module.cividiscount
  cv path -x cividiscount
  cv path -x cividiscount/info.xml
  cv path -x .

Examples: Lookup configuration properties
  cv path -c configAndLogDir
  cv path -c customFileUploadDir
  cv path -c customPHPPathDir
  cv path -c customTemplateDir
  cv path -c extensionsDir
  cv path -c imageUploadDir
  cv path -c uploadDir
  cv path -c templateCompileDir
  cv path -c templateCompileDir/en_US

Examples: Lookup dynamic paths
  cv path -d \'[civicrm.root]\'
  cv path -d \'[civicrm.root]/packages/DB.php\'
  cv path -d \'[civicrm.files]\'
  cv path -d \'[cms.root]/index.php\'

Example: Lookup multiple items
  cv path -x cividiscount/info.xml -x flexmailer/info.xml -d \'[civicrm.root]/civicrm-version.php\'
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!$input->getOption('ext') && !$input->getOption('config') && !$input->getOption('dynamic')) {
      $output->getErrorOutput()->writeln("<error>No paths specified. Must use -x, -c, or -d. (See also: cv path -h)</error>");
      return 1;
    }

    $results = array();
    $returnValue = 0;

    $mapper = \CRM_Extension_System::singleton()->getMapper();
    foreach ($input->getOption('ext') as $extExpr) {
      [$keyOrName, $file] = $this->pathSplit($extExpr);
      if ($keyOrName === '.') {
        $results[] = array(
          'type' => 'ext',
          'expr' => $extExpr,
          'value' => $this->pathJoin(\CRM_Core_Config::singleton()->extensionsDir, $file),
        );
        continue;
      }

      if (strpos($keyOrName, '.') === FALSE) {
        $shortMap = $this->getShortMap();
        if (isset($shortMap[$keyOrName]) && count($shortMap[$keyOrName]) === 1) {
          $keyOrName = $shortMap[$keyOrName][0];
        }
      }

      try {
        $results[] = array(
          'type' => 'ext',
          'expr' => $extExpr,
          'value' => $this->pathJoin($mapper->keyToBasePath($keyOrName), $file),
        );
      }
      catch (\CRM_Extension_Exception_MissingException $e) {
        $output->getErrorOutput()->writeln("<error>Ignoring unrecognized extension \"$keyOrName\"</error>");
        $returnValue = 1;
      }
    }

    foreach ($input->getOption('config') as $configExpr) {
      [$configProperty, $file] = $this->pathSplit($configExpr);
      $dir = \CRM_Core_Config::singleton()->{$configProperty};
      if (version_compare(\CRM_Utils_System::version(), '4.7', '<') && $configProperty === 'templateCompileDir') {
        // Compatibility: 4.6 has weird notion of templates_c.
        $dir = dirname($dir);
      }
      $results[] = array(
        'type' => 'config',
        'expr' => $configExpr,
        'value' => $this->pathJoin($dir, $file),
      );
    }

    foreach ($input->getOption('dynamic') as $dynExpr) {
      if (!is_callable(array('Civi', 'paths'))) {
        $output->getErrorOutput()->writeln("<error>Dynamic path expressions are only available on CiviCRM v4.7+</error>");
        $returnValue = 1;
        break;
      }

      if (preg_match(';^(\[[^\]]+\])([\\/]?)$;', $dynExpr, $matches)) {
        // getPath() is wonky about "[civicrm.root]" or "[civicrm.root]/",
        // so we have to trick it.
        $dyn = \Civi::paths()->getPath($matches[1] . "/./");
        $value = preg_replace(';/./$;', '', $dyn) . $matches[2];
        $results[] = array(
          'type' => 'dynamic',
          'expr' => $dynExpr,
          'value' => $value,
        );
      }
      else {
        // Phew, we can do a normal lookup.
        $results[] = array(
          'type' => 'dynamic',
          'expr' => $dynExpr,
          'value' => \Civi::paths()->getPath($dynExpr),
        );
      }
    }

    $columns = $this->parseColumns($input, array(
      'list' => array('value'),
    ));

    if ($input->getOption('mkdir')) {
      foreach ($results as $result) {
        if (!file_exists($result['value'])) {
          mkdir($result['value'], 0777, TRUE);
        }
      }
    }

    $this->sendTable($input, $output, $results, $columns);
    return $returnValue;
  }

  protected function pathSplit(string $expr): array {
    $parts = explode('/', $expr);
    $first = array_shift($parts);
    return [$first, count($parts) ? implode('/', $parts) : NULL];
  }

  protected function pathJoin($folder, $file) {
    if ($file !== NULL && $file !== FALSE) {
      return \CRM_Utils_File::addTrailingSlash($folder) . $file;
    }
    else {
      return rtrim($folder, DIRECTORY_SEPARATOR);
    }
  }

}
