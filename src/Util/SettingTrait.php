<?php

namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait SettingTrait {

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Civi\Core\SettingsBag $settingsBag
   * @param array $meta
   * @return array
   */
  protected function parseSettingParams(InputInterface $input, \Civi\Core\SettingsBag $settingsBag, array $meta): array {
    $args = $input->getArgument('key=value');
    switch ($input->getOption('in')) {
      case 'args':
        $p = new SettingArgParser($settingsBag, $meta);
        $params = $p->parse($args);
        break;

      case 'json':
        $json = stream_get_contents(STDIN);
        $params = empty($json) ? [] : json_decode($json, TRUE);
        break;

      default:
        throw new \RuntimeException('Unknown input format');
    }

    return $params;
  }

  /**
   * Find the settings-bag(s) for the given scope.
   *
   * @param string $scope
   *   Ex: 'domain', 'contact', 'domain:123', 'contact:123', 'domain:*'
   * @return \Civi\Core\SettingsBag[]
   * @throws \CRM_Core_Exception
   */
  protected function findSettings(string $scope): array {
    if ($scope === 'domain') {
      return [$scope => \Civi::settings()];
    }
    if ($scope === 'contact') {
      return [$scope => \Civi::contactSettings()];
    }

    $parts = explode(':', $scope);
    if ($parts[0] === 'contact' && is_numeric($parts[1])) {
      return [$scope => \Civi::contactSettings($parts[1])];
    }
    if ($parts[0] === 'domain' && is_numeric($parts[1])) {
      return [$scope => \Civi::settings($parts[1])];
    }
    if ($parts[0] === 'domain' && $parts[1] === '*') {
      $domainIds = \CRM_Core_DAO::executeQuery('SELECT id FROM civicrm_domain')->fetchMap('id', 'id');
      $result = [];
      foreach ($domainIds as $domainId) {
        $result["domain:$domainId"] = \Civi::settings($domainId);
      }
      return $result;
    }

    throw new \RuntimeException("Malformed scope");
  }

  public function sendSettings(InputInterface $input, OutputInterface $output, array $result): void {
    $errorOutput = is_callable([$output, 'getErrorOutput']) ? $output->getErrorOutput() : $output;

    $result = $this->fillMeta($result);

    $maxColWidth = 40;
    $abridged = FALSE;
    if (in_array($input->getOption('out'), ['table'])) {
      if ($output->isVerbose()) {
        $this->showVerboseReport($input, $output, $result);
        return;
      }

      foreach ($result as &$row) {
        foreach (['value', 'default'] as $field) {
          $row[$field] = json_encode($row[$field], JSON_UNESCAPED_SLASHES);
          if (!$output->isVerbose() && mb_strlen($row[$field]) > $maxColWidth) {
            $abridged = TRUE;
            $row[$field] = mb_substr($row[$field], 0, $maxColWidth) . '...';
          }
        }
      }
    }

    $this->sendStandardTable($result);
    if ($abridged) {
      $errorOutput->writeln('<comment>NOTE: Some values were truncated for readability. To see full data, use "-v" or "--out=json".</comment>');
    }
  }

  /**
   * @param string $scope
   *  Ex: 'contact', 'domain', 'contact:123', 'domain:123'
   * @return array
   */
  protected function getMetadata(string $scope): array {
    if (preg_match('/^domain:(.*)$/', $scope, $m)) {
      return \Civi\Core\SettingsMetadata::getMetadata([], $m[1]);
    }
    else {
      return \Civi\Core\SettingsMetadata::getMetadata();
    }
  }

  /**
   * @param array $meta
   * @param string $field
   * @return array
   *   Pair of callables: [$encode, $decode]
   */
  protected function codec(array $meta, string $field) {
    return SettingCodec::codec($meta, $field);
  }

  protected function fillMeta(array $result): array {
    foreach ($result as &$row) {
      $meta = $this->getMetadata($row['scope'])[$row['key']] ?? [];
      foreach ($meta as $key => $value) {
        $row["meta.$key"] = $value;
      }
    }
    return $result;
  }

  protected function showVerboseReport(InputInterface $input, OutputInterface $output, array $result): void {
    foreach ($result as $row) {
      $verboseTable = [];
      foreach ($row as $key => $value) {
        $verboseTable[] = ['key' => $key, 'value' => $value];
      }
      $this->sendTable($input, $output, $verboseTable);
    }
  }

  /**
   * @param $names
   *
   * @return \Closure
   */
  protected function createSettingFilter($names): \Closure {
    $filterList = [];
    foreach ($names as $filterPat) {
      if ($filterPat[0] === '/') {
        if (!preg_match(';/i?$;', $filterPat)) {
          throw new \RuntimeException('Malformed regular expression. (There may be a missing delimiter or invalid modifier.)');
        }
        $filterList[] = $filterPat;
      }
      else {
        $filterList[] = '/^' . preg_quote($filterPat, '/') . '$/';
      }
    }

    if (empty($filterList)) {
      $filter = function (string $name) {
        return TRUE;
      };
    }
    else {
      $filterExpr = '/' . implode('|', $filterList) . '/';
      $filter = function (string $name) use ($filterList) {
        foreach ($filterList as $filterExpr) {
          if (preg_match($filterExpr, $name)) {
            return TRUE;
          }
        }
        return FALSE;
      };
    }
    return $filter;
  }

}
