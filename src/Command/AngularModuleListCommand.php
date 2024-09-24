<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AngularModuleListCommand extends CvCommand {

  use StructuredOutputTrait;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ang:module:list')
      ->setAliases(array())
      ->setDescription('List Angular modules')
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'table', 'defaultColumns' => 'name,basePages,requires', 'shortcuts' => TRUE])
      ->addArgument('regex', InputArgument::OPTIONAL,
        'Filter extensions by full key or short name')
      ->setHelp('List Angular modules

Examples:
  cv ang:module:list
  cv ang:module:list /crmUi/
  cv ang:module:list --columns=name,ext,extDir
  cv ang:module:list \'/crmMail/\' --user=admin --columns=extDir,css
  cv ang:module:list --columns=name,js,css --out=json-pretty
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!$input->getOption('user')) {
      $output->getErrorOutput()->writeln("<comment>For a full list, try passing --user=[username].</comment>");
    }

    $this->sendStandardTable($this->find($input));
    return 0;
  }

  /**
   * Find extensions matching the input args.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   */
  protected function find($input) {
    $regex = $input->getArgument('regex');
    $ang = \Civi::service('angular');
    $rows = array();

    foreach ($ang->getModules() as $name => $module) {
      $resources = array();
      foreach (array('js', 'partials', 'css', 'settings') as $key) {
        if (!empty($module[$key])) {
          $resources[] = sprintf("%s(%d)", $key, count($module[$key]));
        }
      }

      if (!isset($module['basePages'])) {
        $basePages = 'civicrm/a';
      }
      elseif (empty($module['basePages'])) {
        $basePages = '(as-needed)';
      }
      else {
        $basePages = implode(", ", $module['basePages']);
      }

      $rows[] = array(
        'name' => $name,
        'ext' => $module['ext'],
        'extDir' => \CRM_Core_Resources::singleton()->getPath($module['ext'], NULL),
        'resources' => implode(', ', $resources),
        'basePages' => $basePages,
        'js' => isset($module['js']) ? implode(", ", $module['js']) : '',
        'css' => isset($module['css']) ? implode(", ", $module['css']) : '',
        'partials' => isset($module['partials']) ? implode(", ", $module['partials']) : '',
        'requires' => isset($module['requires']) ? implode(', ', $module['requires']) : '',
      );
    }

    $rows = array_filter($rows, function ($row) use ($regex) {
      if ($regex) {
        if (!preg_match($regex, $row['ext']) && !preg_match($regex, $row['name'])) {
          return FALSE;
        }
      }
      return TRUE;
    });

    return $rows;
  }

}
