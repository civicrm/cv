<?php

namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends CvCommand {

  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('status')
      ->setDescription('Provide an overview of current site/environment')
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'table']);

  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $isPhar = preg_match(';^phar://;', __FILE__);
    $civiCodeVer = \CRM_Utils_System::version();
    $civiDbVer = \CRM_Core_BAO_Domain::version();
    $mysqlVersion = \CRM_Utils_SQL::getDatabaseVersion();
    $ufType = strtolower((CIVICRM_UF === 'Drupal8') ? 'Drupal' : CIVICRM_UF);
    $ufVer = \CRM_Core_Config::singleton()->userSystem->getVersion();
    if (method_exists(\CRM_Core_Smarty::singleton(), 'getVersion')) {
      $smartyVer = \CRM_Core_Smarty::singleton()->getVersion();
    }
    else {
      $smartyVer = 'Unknown';
    }

    $summaryCode = sprintf('%s%s %s%s %s %s',
      $civiCodeVer,
      ($civiCodeVer === $civiDbVer) ? '' : '**',
      $this->shortPhp(PHP_VERSION),
      $this->shortDbms($mysqlVersion),
      $this->shortCms(CIVICRM_UF, $ufVer),
      strtolower(php_uname('s'))
    );

    $data = [];
    $data['summary'] = $summaryCode;
    $data['civicrm'] = ($civiDbVer === $civiCodeVer) ? "$civiCodeVer" : "$civiCodeVer (DB $civiDbVer)";
    $data['cv'] = Application::version() . ($isPhar ? ' (phar)' : ' (src)');
    $data['php'] = sprintf('%s (%s)', PHP_VERSION, PHP_SAPI);
    $data['mysql'] = $mysqlVersion;
    $data[$ufType] = $ufVer;
    $data['os'] = php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m');
    // Would be nice to get lsb_release, but that requires more conditionality
    $data['smarty'] = $smartyVer;
    $data['path: cms.root'] = \Civi::paths()->getPath('[cms.root]/.');
    $data['path: civicrm.root'] = \Civi::paths()->getPath('[civicrm.root]/.');
    $data['path: civicrm.log'] = \Civi::paths()->getPath('[civicrm.log]/.');
    $data['path: civicrm.l10n'] = \Civi::paths()->getPath('[civicrm.l10n]/.');
    $data['path: extensionsDir'] = \CRM_Core_Config::singleton()->extensionsDir;

    $rows = [];
    foreach ($data as $name => $value) {
      $rows[$name] = ['name' => $name, 'value' => $value];
    }

    $this->sendTable($input, $output, $rows);
    return 0;
  }

  private function shortPhp($version): string {
    return 'php' . preg_replace('/([0-9]+)\.([0-9]+).*$/', '$1$2', $version);
  }

  private function shortDbms($version): string {
    if (str_contains($version, 'Maria')) {
      // FIXME: ex: 10.5 ==> r105
      return 'r???';
    }
    else {
      return 'm' . preg_replace('/([0-9]+)\.([0-9]+).*$/', '$1$2', $version);
    }
  }

  private function shortCms($ufName, $ufVersion): string {
    switch ($ufName) {
      case 'Drupal':
      case 'Drupal8':
        return 'drupal' . explode('.', $ufVersion)[0];

      case 'WordPress':
        return 'wp';

      case 'Joomla':
        return 'joomla' . explode('.', $ufVersion)[0];

      case 'Backdrop':
        return 'backdrop';

      case 'Standalone':
        return 'standalone';

      default:
        return $ufName;
    }
  }

}
