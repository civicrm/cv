<?php

namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\InputInterface;

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

}
