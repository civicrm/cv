<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\ArrayUtil;
use Civi\Cv\Util\ExtensionUtil;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class AngularModuleListCommand extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;

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
      ->addOption('columns', NULL, InputOption::VALUE_REQUIRED,
        'List of columns to display (comma separated)',
        'name,basePages,requires')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED,
        'Output format (' . implode(',', Encoder::getTabularFormats()) . ')',
        Encoder::getDefaultFormat('table'))
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
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    if (!$input->getOption('user')) {
      $output->getErrorOutput()->writeln("<comment>For a full list, try passing --user=[username].</comment>");
    }

    $columns = explode(',', $input->getOption('columns'));
    $records = $this->sort($this->find($input), $columns);

    $this->sendTable($input, $output, $records, $columns);

    return 0;
  }

  /**
   * Find extensions matching the input args.
   *
   * @param InputInterface $input
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

  protected function sort($rows, $orderByColumns) {
    usort($rows, function ($a, $b) use ($orderByColumns) {
      foreach ($orderByColumns as $col) {
        if ($a[$col] < $b[$col]) {
          return -1;
        }
        if ($a[$col] > $b[$col]) {
          return 1;
        }
      }

      return 0;
    });

    return $rows;
  }

}
