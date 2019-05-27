<?php
namespace Civi\Cv\Util;

use Civi\Cv\Encoder;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StructuredOutputTrait
 * @package Civi\Cv\Util
 *
 * This trait helps with implementing a command in which the output is
 * structured and user-configurable -- e.g. a user may choose to display
 * output as JSON, PHP code, shell variables, CSV, or a colorful table.
 */
trait StructuredOutputTrait {

  /**
   * Register CLI options related to output.
   *
   * Ex:
   *   $this->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'json-pretty']);
   *
   * @param array $config
   *   Any mix of the following options:
   *   - tabular: bool, this command supports tabular formats (such as CSV)
   *     (Default: FALSE)
   *   - fallback: string, the format to use if the inputs+environment do not
   *     specify a format. (Default: json-pretty)
   *   - defaultColumns: string|NULL, a comma-separated list of default columns to display
   *   - availColumns: string|NULL, a comma-separated list of columns which may be displayed
   *
   * NOTE: The --columns option will only defined if 'defaultColumns' or/and 'availColumns'
   * is passed.
   *
   * @return $this
   */
  protected function configureOutputOptions($config = []) {
    $formats = !empty($config['tabular']) ? Encoder::getTabularFormats() : Encoder::getFormats();
    $fallback = !empty($config['fallback']) ? $config['fallback'] : 'json-pretty';

    $this->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', $formats) . ')', Encoder::getDefaultFormat($fallback));

    if (array_key_exists('defaultColumns', $config) || array_key_exists('availColumns', $config)) {
      $defaultValue = array_key_exists('defaultColumns', $config) ? $config['defaultColumns'] : NULL;
      $desc = 'Comma-separated list of columns to display';
      if (!empty($config['availColumns'])) {
        $desc .= ' <comment>[available: ' . $config['availColumns'] . ']</comment>';
      }
      $this->addOption('columns', NULL, InputOption::VALUE_REQUIRED, $desc, $defaultValue);
    }

    return $this;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param mixed $result
   * @see Encoder::getFormats
   */
  protected function sendResult(InputInterface $input, OutputInterface $output, $result) {
    $buf = Encoder::encode($result, $input->getOption('out'));
    $options = empty($result['is_error'])
      ? (OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_NORMAL)
      : (OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    $output->writeln($buf, $options);
  }

  /**
   * Send tabular data.
   *
   * This is very similar to sendResult() in that it adapts the output format
   * to the user preference. However, it supports some additional formats, and
   * it has the constraint that $result must contain tabular data.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param array $records
   *   $result[0] == array('color' => 'red', 'length' => 5);
   *   $result[1] == array('color' => 'blue', 'length' => 2);
   * @param array $columns
   *   List of columns to display.
   * @see Encoder::getTabularFormats
   */
  protected function sendTable(InputInterface $input, OutputInterface $output, $records, $columns = NULL) {
    // Maybe we should standardize '--columns=...' so it doesn't ned to be passed in?

    if (is_array($columns) && in_array('*', $columns)) {
      $columns = NULL;
    }

    switch ($input->getOption('out')) {
      case 'table':
        // Display a pleasant-looking table.
        $columns = $columns ? $columns : ArrayUtil::findColumns($records);
        $table = new Table($output);
        $table->setHeaders($columns);
        $table->addRows(ArrayUtil::convertAssocToNum($records, $columns));
        $table->render();
        break;

      case 'csv':
        // Display CSV-formatted table.
        $columns = $columns ? $columns : ArrayUtil::findColumns($records);
        // FIXME Link fputcsv to $output
        fputcsv(STDOUT, $columns);
        foreach (ArrayUtil::convertAssocToNum($records, $columns) as $record) {
          fputcsv(STDOUT, $record);
        }
        break;

      case 'list':
        // Display a flat list from the first column.
        $columns = $columns ? $columns : ArrayUtil::findColumns($records);
        foreach ($records as $record) {
          $output->writeln($record[$columns[0]], OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_NORMAL);
        }
        break;

      default:
        // Use a generic format.
        $this->sendResult($input, $output,
          $columns ? ArrayUtil::filterColumns($records, $columns) : $records);
        break;
    }
  }

  /**
   * Determine the columns to display.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param array $defaultColumns
   *   Ex: $defaultColumns['table'] = array('expr', 'value').
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
