<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AngularHtmlListCommand extends CvCommand {

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
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'list', 'defaultColumns' => 'file', 'shortcuts' => TRUE])
      ->addArgument('filter', InputArgument::OPTIONAL,
        'Filter by filename. For regex filtering, use semicolon delimiter.')
      ->setHelp('List Angular HTML files

Examples:
  cv ang:html:list
  cv ang:html:list crmUi/*
  cv ang:html:list \';(tabset|wizard)\\.html;\'
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
