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


class ExtensionListCommand extends BaseExtensionCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:list')
      ->setAliases(array())
      ->setDescription('List extensions')
      ->addOption('local', 'L', InputOption::VALUE_NONE, 'Filter extensions by location (local)')
      ->addOption('remote', 'R', InputOption::VALUE_NONE, 'Filter extensions by location (remote)')
      ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Refresh the list of extensions')
      ->addOption('installed', 'i', InputOption::VALUE_NONE, 'Filter extensions by "installed" status (Equivalent to --statuses=installed)')
      ->addOption('statuses', NULL, InputOption::VALUE_REQUIRED, 'Filter extensions by status (comma separated)', '*')
      ->addOption('columns', NULL, InputOption::VALUE_REQUIRED, 'List of columns to display (comma separated)', 'location,key,name,version,status,downloadUrl')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getTabularFormats()) . ')', Encoder::getDefaultFormat('table'))
      ->addArgument('regex', InputArgument::OPTIONAL, 'Filter extensions by full key or short name')
      ->setHelp('List extensions

Examples:
  cv ext:list
  cv ext:list --remote --dev /mail/
  cv ext:list /^org.civicrm.*/
  cv ext:list -Li --columns=key,label

Note:
  If you do not specify --local (-L) or --remote (-R), then all are listed.

  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.
');
    parent::configureRepoOptions();
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $wo = ($input->getOption('out') === 'table')
      ? (OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_NORMAL)
      : (OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_VERBOSE);

    list($local, $remote) = $this->parseLocalRemote($input);

    if ($extRepoUrl = $this->parseRepoUrl($input)) {
      global $civicrm_setting;
      $civicrm_setting['Extension Preferences']['ext_repo_url'] = $extRepoUrl;
    }

    $this->boot($input, $output);

    if ($remote) {
      $output->writeln("<info>Using extension feed \"" . \CRM_Extension_System::singleton()->getBrowser()->getRepositoryUrl() . "\"</info>", $wo);
    }

    if ($input->getOption('refresh')) {
      $output->writeln("<info>Refreshing extensions</info>", $wo);
      $result = $this->callApiSuccess($input, $output, 'Extension', 'refresh', array(
        'local' => $local,
        'remote' => $remote,
      ));
      if (!empty($result['is_error'])) {
        return 1;
      }
    }

    $columns = explode(',', $input->getOption('columns'));
    $records = $this->sort($this->find($input), $columns);

    $this->sendTable($input, $output, $records, $columns);

    return 0;
  }

  /**
   * Get a list of all available extensions.
   *
   * @return array
   *   ($key => CRM_Extension_Info)
   */
  protected function getRemoteInfos() {
    static $cache = NULL;
    if ($cache === NULL) {
      $cache = \CRM_Extension_System::singleton()
        ->getBrowser()->getExtensions();
    }
    return $cache;
  }

  /**
   * Find extensions matching the input args.
   *
   * @param InputInterface $input
   * @return array
   */
  protected function find($input) {
    $regex = $input->getArgument('regex');
    list($local, $remote) = $this->parseLocalRemote($input);

    if ($input->getOption('installed')) {
      $statusFilter = array('installed');
    }
    elseif ($input->getOption('statuses') && $input->getOption('statuses') !== '*') {
      $statusFilter = explode(',', $input->getOption('statuses'));
    }
    else {
      $statusFilter = NULL;
    }

    $rows = array();

    if ($remote) {
      foreach ($this->getRemoteInfos() as $info) {
        $rows[] = array(
          'location' => 'remote',
          'key' => $info->key,
          'name' => $info->file,
          'version' => $info->version,
          'label' => $info->label,
          'status' => '',
          'type' => $info->type,
          'path' => '',
          'downloadUrl' => $info->downloadUrl,
        );
      }
    }

    if ($local) {
      $keys = \CRM_Extension_System::singleton()->getFullContainer()->getKeys();
      $statuses = \CRM_Extension_System::singleton()->getManager()->getStatuses();
      $mapper = \CRM_Extension_System::singleton()->getMapper();
      foreach ($keys as $key) {
        $info = $mapper->keyToInfo($key);
        $rows[] = array(
          'location' => 'local',
          'key' => $key,
          'name' => $info->file,
          'version' => $info->version,
          'label' => $info->label,
          'status' => isset($statuses[$key]) ? $statuses[$key] : '',
          'type' => $info->type,
          'path' => $mapper->keyToBasePath($key),
          'downloadUrl' => $info->downloadUrl,
        );
      }
    }

    $rows = array_filter($rows, function ($row) use ($regex, $statusFilter) {
      if ($statusFilter !== NULL && !in_array($row['status'], $statusFilter)) {
        return FALSE;
      }
      if ($regex) {
        if (!preg_match($regex, $row['key']) && !preg_match($regex, $row['name'])) {
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

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   */
  protected function parseLocalRemote(InputInterface $input) {
    if ($input->getOption('local') || $input->getOption('remote')) {
      $local = (bool) $input->getOption('local');
      $remote = (bool) $input->getOption('remote');
      return array($local, $remote);
    }
    else {
      $local = $remote = TRUE;
      return array($local, $remote);
    }
  }

}
