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

  protected function configureBootOptions() {
    $this->addOption('level', NULL, InputOption::VALUE_REQUIRED, 'Bootstrap level (classloader,settings,full)', 'full');
    $this->addOption('test', 't', InputOption::VALUE_NONE, 'Bootstrap the test database (CIVICRM_UF=UnitTests)');
    $this->addOption('user', 'U', InputOption::VALUE_REQUIRED, 'CMS user');
  }

  protected function boot(InputInterface $input, OutputInterface $output) {
    if ($output->isDebug()) {
      $output->writeln(
        'Attempting to set verbose error reporting',
        OutputInterface::VERBOSITY_DEBUG);
      // standard php debug chat settings
      error_reporting(E_ALL | E_STRICT);
      ini_set('display_errors', TRUE);
      ini_set('display_startup_errors', TRUE);
      // add the output object to allow the bootstrapper to output debug messages
      // and track verboisty
      $boot_params = array(
        'output' => $output,
      );
    }
    else {
      $boot_params = array();
    }

    $output->writeln('<info>[BaseCommand::boot]</info> Start', OutputInterface::VERBOSITY_DEBUG);

    if ($input->hasOption('test') && $input->getOption('test')) {
      $output->writeln('<info>[BaseCommand::boot]</info> Use test mode', OutputInterface::VERBOSITY_DEBUG);
      putenv('CIVICRM_UF=UnitTests');
      $_ENV['CIVICRM_UF'] = 'UnitTests';
    }

    if ($input->hasOption('level') && $input->getOption('level') !== 'full') {
      $output->writeln('<info>[BaseCommand::boot]</info> Call basic cv bootstrap (' . $input->getOption('level') . ')', OutputInterface::VERBOSITY_DEBUG);
      \Civi\Cv\Bootstrap::singleton()->boot($boot_params + array(
        'prefetch' => FALSE,
      ));
    }
    else {
      $output->writeln('<info>[BaseCommand::boot]</info> Call standard cv bootstrap', OutputInterface::VERBOSITY_DEBUG);
      \Civi\Cv\Bootstrap::singleton()->boot($boot_params);

      $output->writeln('<info>[BaseCommand::boot]</info> Call core bootstrap', OutputInterface::VERBOSITY_DEBUG);
      \CRM_Core_Config::singleton();

      $output->writeln('<info>[BaseCommand::boot]</info> Call CMS bootstrap', OutputInterface::VERBOSITY_DEBUG);
      \CRM_Utils_System::loadBootStrap(array(), FALSE);

      if ($input->getOption('user')) {
        $output->writeln('<info>[BaseCommand::boot]</info> Set system user', OutputInterface::VERBOSITY_DEBUG);
        if (is_callable(array(\CRM_Core_Config::singleton()->userSystem, 'loadUser'))) {
          \CRM_Core_Config::singleton()->userSystem->loadUser($input->getOption('user'));
          if (!$this->ensureUserContact($output)) {
            throw new \Exception("Failed to determine contactID for user=" . $input->getOption('user'));
          }
        }
        else {
          $output->getErrorOutput()->writeln("<error>Failed to set user. Feature not supported by UF (" . CIVICRM_UF . ")</error>");
        }
      }
    }

    $output->writeln('<info>[BaseCommand::boot]</info> Finished', OutputInterface::VERBOSITY_DEBUG);
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
   * Ensure that the current user has a contact record.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return int|NULL
   *   The user's contact ID, or NULL
   */
  private function ensureUserContact(OutputInterface $output) {
    if ($cid = \CRM_Core_Session::getLoggedInContactID()) {
      return $cid;
    }

    // Ugh, this codepath is ridiculous.
    switch (CIVICRM_UF) {
      case 'Drupal':
      case 'Drupal6':
      case 'Backdrop':
        \CRM_Core_BAO_UFMatch::synchronize($GLOBALS['user'], TRUE,
          CIVICRM_UF, 'Individual');
        break;

      case 'Drupal8':
        \CRM_Core_BAO_UFMatch::synchronize(\Drupal::currentUser(), TRUE,
          CIVICRM_UF, 'Individual');
        break;

      case 'Joomla':
        \CRM_Core_BAO_UFMatch::synchronize(\JFactory::getUser(), TRUE,
          CIVICRM_UF, 'Individual');
        break;

      case 'WordPress':
        \CRM_Core_BAO_UFMatch::synchronize($GLOBALS['current_user'], TRUE,
          CIVICRM_UF, 'Individual');
        break;

      default:
        $output->writeln("<error>Unrecognized UF: " . CIVICRM_UF . "</error>");
    }

    return \CRM_Core_Session::getLoggedInContactID();
  }

}
