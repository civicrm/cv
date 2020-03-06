<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\BootTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AngularHtmlListCommand extends BaseCommand {

  use BootTrait;
  use StructuredOutputTrait;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ang:html:list')
      ->setAliases(array())
      ->setDescription('List Angular HTML files')
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'list', 'defaultColumns' => 'file'])
      ->addArgument('filter', InputArgument::OPTIONAL,
        'Filter by filename. For regex filtering, use semicolon delimiter.')
      ->setHelp('List Angular HTML files

Examples:
  cv ang:html:list
  cv ang:html:list crmUi/*
  cv ang:html:list \';(tabset|wizard)\\.html;\'
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   */
  protected function find($input) {
    $regex = $input->getArgument('filter') ? $this->createRegex($input->getArgument('filter')) : NULL;
    $ang = \Civi::service('angular');
    $rows = array();

    foreach ($ang->getModules() as $name => $module) {
      $partials = $ang->getPartials($name);
      foreach ($partials as $file => $html) {
        $rows[] = array(
          'file' => preg_replace(';^~/;', '', $file),
          'module' => $name,
          'ext' => $module['ext'],
        );
      }
    }

    $rows = array_filter($rows, function ($row) use ($regex) {
      if ($regex) {
        if (!preg_match($regex, $row['file'])) {
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

  protected function createRegex($filterExpr) {
    if ($filterExpr[0] === ';') {
      return $filterExpr;
    }
    // $filterExpr = preg_replace(';^~/;', '', $filterExpr);
    $regex = preg_quote($filterExpr, ';');
    $regex = str_replace('\\*', '[^/]*', $regex);
    $regex = ";^$regex.*$;";
    return $regex;
  }

}
