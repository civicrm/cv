<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\Json;
use Civi\Cv\SiteConfigReader;
use Civi\Cv\Util\ArrayUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class BaseCommand extends Command {

  protected function assertBooted() {
    if (!$this->isBooted()) {
      throw new \Exception("Error: This command requires bootstrapping, but the system does not appear to be bootstrapped. Perhaps you set --level=none?");
    }
  }

  /**
   * Execute an API call. If it fails, display a formatted error.
   *
   * Note: If there is an error, we still return it softly so that the
   * command can exit gracefully.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $entity
   * @param $action
   * @param $params
   * @return mixed
   */
  protected function callApiSuccess(InputInterface $input, OutputInterface $output, $entity, $action, $params) {
    $this->assertBooted();
    $params['debug'] = 1;
    if (!isset($params['version'])) {
      $params['version'] = 3;
    }
    $output->writeln("Calling $entity $action API", OutputInterface::VERBOSITY_DEBUG);
    $result = \civicrm_api($entity, $action, $params);
    if (!empty($result['is_error']) || $output->isDebug()) {
      $data = array(
        'entity' => $entity,
        'action' => $action,
        'params' => $params,
        'result' => $result,
      );
      if (!empty($result['is_error'])) {
        $output->getErrorOutput()->writeln("<error>Error: API Call Failed</error>: "
          . Encoder::encode($data, 'pretty'));
      }
      else {
        $output->writeln("API success" . Encoder::encode($data, 'pretty'),
          OutputInterface::VERBOSITY_DEBUG);
      }
    }
    return $result;
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
   * Parse an option's data. This is for options where the default behavior
   * (of total omission) differs from the activated behavior
   * (of an active but unspecified option).
   *
   * Example, suppose we want these interpretations:
   *   cv en         ==> Means "--refresh=auto"; see $omittedDefault
   *   cv en -r      ==> Means "--refresh=yes"; see $activeDefault
   *   cv en -r=yes  ==> Means "--refresh=yes"
   *   cv en -r=no   ==> Means "--refresh=no"
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param array $rawNames
   *   Ex: array('-r', '--refresh').
   * @param string $omittedDefault
   *   Value to use if option is completely omitted.
   * @param string $activeDefault
   *   Value to use if option is activated without data.
   * @return string
   */
  public function parseOptionalOption(InputInterface $input, $rawNames, $omittedDefault, $activeDefault) {
    $value = NULL;
    foreach ($rawNames as $rawName) {
      if ($input->hasParameterOption($rawName)) {
        if (NULL === $input->getParameterOption($rawName)) {
          return $activeDefault;
        }
        else {
          return $input->getParameterOption($rawName);
        }
      }
    }
    return $omittedDefault;
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

  /**
   * @return bool
   */
  protected function isBooted() {
    return defined('CIVICRM_DSN');
  }

}
