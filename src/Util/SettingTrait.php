<?php

namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait SettingTrait {

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param $matches
   * @return array
   */
  protected function parseSettingParams(InputInterface $input) {
    $args = $input->getArgument('key=value');
    switch ($input->getOption('in')) {
      case 'args':
        $p = new Api4ArgParser();
        $params = $p->parse($args);
        $params = $this->castNumbers($params);
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

  private function castNumbers(array $params): array {
    foreach ($params as $key => &$value) {
      if (is_string($value) && preg_match(';^\d+$;', $value)) {
        $value = (int) $value;
      }
      elseif (is_string($value) && preg_match(';^\d+\.\d+$;', $value)) {
        $value = (float) $value;
      }
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

    usort($result, function ($a, $b) {
      return strcmp($a['key'], $b['key']);
    });

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

    $columns = explode(',', $input->getOption('columns'));

    $this->sendTable($input, $output, $result, $columns);
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
    // There are a handful of  settings with a secondary/nested encoding.
    $encode = function($value) use ($meta, $field) {
      if (isset($value) && !empty($meta[$field]['serialize'])) {
        return \CRM_Core_DAO::serializeField($value, $meta[$field]['serialize']);
      }
      return $value;
    };
    $decode = function($value) use ($meta, $field) {
      if (isset($value) && !empty($meta[$field]['serialize'])) {
        return \CRM_Core_DAO::unSerializeField($value, $meta[$field]['serialize']);
      }
      return $value;
    };
    return [$encode, $decode];
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
        $filterList[] = substr($filterPat, 1, -1);
      }
      else {
        $filterList[] = '^' . preg_quote($filterPat, '/') . '$';
      }
    }

    if (empty($filterList)) {
      $filter = function (string $name) {
        return TRUE;
      };
    }
    else {
      $filterExpr = '/' . implode('|', $filterList) . '/';
      $filter = function (string $name) use ($filterExpr) {
        return (bool) preg_match($filterExpr, $name);
      };
    }
    return $filter;
  }

}
