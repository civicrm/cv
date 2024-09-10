<?php
namespace Civi\Cv\Command;

use Civi\Cv\Cv;
use Civi\Cv\Util\ArrayUtil;
use Civi\Cv\Util\Relativizer;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtensionListCommand extends BaseExtensionCommand {

  use StructuredOutputTrait;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:list')
      ->setAliases(['ext'])
      ->setDescription('List extensions')
      ->addOption('local', 'L', InputOption::VALUE_NONE, 'Filter extensions by location (local)')
      ->addOption('remote', 'R', InputOption::VALUE_NONE, 'Filter extensions by location (remote)')
      ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Refresh the list of extensions')
      ->addOption('installed', 'i', InputOption::VALUE_NONE, 'Filter extensions by "installed" status (Equivalent to --statuses=installed)')
      ->addOption('statuses', NULL, InputOption::VALUE_REQUIRED, 'Filter extensions by status (comma separated)', '*')
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'table', 'defaultColumns' => 'location,key,name,version,status,downloadUrl', 'shortcuts' => TRUE])
      ->addArgument('regex', InputArgument::OPTIONAL, 'Filter extensions by full key or short name')
      ->setHelp('List extensions

Examples:
  cv ext:list
  cv ext:list --remote --dev /mail/
  cv ext:list /^org.civicrm.*/
  cv ext:list -Li --columns=key,label
  cv ext:list -Ra /mosaico/

Note:
  If you do not specify --local (-L) or --remote (-R), then both are listed.

  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.
');
    parent::configureRepoOptions();
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
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

    $records = $this->find($input);
    $columns = ArrayUtil::resolveColumns($this->parseColumns($input), $records);
    $records = $this->applyExtras($records, $columns);
    $this->sendStandardTable($records);
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
   * @param \Symfony\Component\Console\Input\InputInterface $input
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
          'nameKey' => $this->formatInlineAlias($info->file, $info->key),
          'version' => $info->version,
          'label' => $info->label,
          'status' => '',
          'type' => $info->type,
          'path' => '',
          'relPath' => '',
          'downloadUrl' => $info->downloadUrl,
          'upgrade' => '',
          'upgradeVersion' => '',
        );
      }
    }

    if ($local) {
      $keys = \CRM_Extension_System::singleton()->getFullContainer()->getKeys();
      $statuses = \CRM_Extension_System::singleton()->getManager()->getStatuses();
      $mapper = \CRM_Extension_System::singleton()->getMapper();
      $remotes = $this->getRemoteInfos();
      $relativizer = new Relativizer();
      foreach ($keys as $key) {
        $info = $mapper->keyToInfo($key);
        $localPath = $mapper->keyToBasePath($key);
        $rows[] = array(
          'location' => 'local',
          'key' => $key,
          'name' => $info->file,
          'nameKey' => $this->formatInlineAlias($info->file, $info->key),
          'version' => $info->version,
          'label' => $info->label,
          'status' => isset($statuses[$key]) ? $statuses[$key] : '',
          'type' => $info->type,
          'path' => $localPath,
          'relPath' => $localPath ? $relativizer->filter($localPath) : '',
          'downloadUrl' => property_exists($info, 'downloadUrl') ? $info->downloadUrl : NULL,
          'upgrade' => $this->getUpgradeStatus($info, $remotes[$key] ?? NULL, $localPath),
          'upgradeVersion' => isset($remotes[$key]->version) && version_compare($remotes[$key]->version, $info->version, '>') ? $remotes[$key]->version : '',
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

  protected function getUpgradeStatus(?\CRM_Extension_Info $localInfo, ?\CRM_Extension_Info $remoteInfo, ?string $localPath): string {
    if (empty($localInfo) || empty($remoteInfo)) {
      return '';
    }

    if (empty($localInfo->version) || empty($remoteInfo->version)) {
      return 'unknown';
    }

    if (version_compare($localInfo->version, $remoteInfo->version, '>=')) {
      return 'current';
    }

    $sys = \CRM_Extension_System::singleton();
    if (!\CRM_Utils_File::isChildPath($sys->getDefaultContainer()->getBaseDir(), $localPath)) {
      // Only try to manage upgrades within the default container.
      // Don't encourage folks to have split-paths.
      return 'manual';
    }
    else {
      return 'available';
    }

    // return version_compare($localInfo->version, $remoteInfo->version, '>=') ? 'current' : 'available';
  }

  protected function getPath(\CRM_Extension_Container_Interface $c, string $key): ?string {
    try {
      return $c->getPath($key);
    }
    catch (\CRM_Extension_Exception_MissingException $e) {
      return NULL;
    }
  }

  protected function applyExtras(array $rows, array $columns): array {
    if (!in_array('extras', $columns)) {
      return $rows;
    }

    foreach ($rows as &$row) {
      $extra = [];

      if (!in_array('upgrade', $columns) && !in_array('upgradeVersion', $columns)) {
        if ($row['upgrade'] === 'available') {
          $extra[] = $this->formatInlineKeyValue('upgrade', $row['upgradeVersion']);
        }
        elseif ($row['upgrade'] === 'manual') {
          $extra[] = $this->formatInlineKeyValue('upgrade', [$row['upgradeVersion'], 'manual']);
        }
      }
      if (!in_array('downloadUrl', $columns) && !empty($row['downloadUrl'])) {
        $extra[] = $this->formatInlineKeyValue('url', parse_url($row['downloadUrl'], PHP_URL_HOST));
      }

      $row['extras'] = implode(' ', $extra);
    }
    return $rows;
  }

  /**
   * Format in an inlined expression with a name (and its alias).
   *
   * @param string $name
   * @param string|null $alias
   * @return string
   */
  private function formatInlineAlias(string $name, ?string $alias): string {
    if ($alias !== NULL && $alias !== '' && $alias !== $name) {
      $suffix = $this->isAnsiEnabled() ? " (<comment>$alias</comment>)" : " ($alias)";
    }
    else {
      $suffix = '';
    }
    return $name . $suffix;
  }

  /**
   * Format an inlined expression with a key-value.
   *
   * @param string $key
   * @param string|string[] $value
   * @return string
   */
  private function formatInlineKeyValue(string $key, $value): string {
    $value = (array) $value;
    if ($this->isAnsiEnabled()) {
      $value = array_map(function($s) {
        return "<comment>$s</comment>";
      }, (array) $value);
    }
    $value = implode(',', $value);
    return "{$key}[$value]";
  }

  private function isAnsiEnabled(): bool {
    $input = Cv::input();
    if ($input->getOption('no-ansi')) {
      return FALSE;
    }
    if ($input->getOption('ansi')) {
      return TRUE;
    }
    $out = $input->getOption('out');
    return ($out === 'table');
  }

}
