<?php

namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Util\StructuredOutputTrait;
use Civi\Test\Invasive;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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
    $data['civicrm'] = $this->longCivi($civiCodeVer, $civiDbVer);
    $data['cv'] = Application::version() . ($isPhar ? ' (phar)' : ' (src)');
    $data['php'] = $this->longPhp();
    $data['mysql'] = $mysqlVersion;
    $data[$ufType] = $ufVer;
    $data['os'] = $this->longOs();
    // Would be nice to get lsb_release, but that requires more conditionality
    $data['smarty'] = $smartyVer;
    $data = array_merge($data, $this->findPathsUrls($output));

    $rows = [];
    foreach ($data as $name => $value) {
      $rows[$name] = ['name' => $name, 'value' => $value];
    }

    $this->sendTable($input, $output, $rows);
    if ($input->getOption('out') === 'table' && !$output->isVerbose()) {
      $error = method_exists($output, 'getErrorOutput') ? $output->getErrorOutput() : $output;
      $error->writeln('<comment>TIP: To see even more information, enable the verbose flag (-v).</comment>');
    }

    return 0;
  }

  private function longCivi($civiCodeVer, $civiDbVer): string {
    if ($civiDbVer === $civiCodeVer) {
      return $civiCodeVer;
    }
    elseif (version_compare($civiDbVer, $civiCodeVer, '<')) {
      return "$civiCodeVer (pending upgrade from $civiDbVer)";
    }
    else {
      return "$civiCodeVer (futuristic data from $civiDbVer)";
    }
  }

  private function longOs(): string {
    $parens = [];

    $p = new Process(['lsb_release', '-sd']);
    $p->run();
    if ($p->isSuccessful() && $output = trim($p->getOutput())) {
      $main = $output;
      $parens[php_uname('s') . ' ' . php_uname('r')] = 1;
    }
    else {
      $main = php_uname('s') . ' ' . php_uname('r');
    }

    $parens[php_uname('m')] = 1;

    if (file_exists('/.dockerenv')) {
      $parens['docker'] = 1;
    }
    if (file_exists('/opt/homebrew')) {
      // Newer deployments use /opt/homebrew. Dunno how to check older deployments in /usr/local.
      $parens['homebrew'] = 1;
    }
    if (file_exists('/nix')) {
      $parens['nix'] = 1;
    }

    return sprintf('%s (%s)', $main, implode(', ', array_keys($parens)));
  }

  private function longPhp(): string {
    $parens = [PHP_SAPI => 1];

    if (file_exists('/.dockerenv')) {
      $parens['docker'] = 1;
    }

    $parens['other'] = 1;
    foreach ([PHP_BINARY, realpath(PHP_BINARY)] as $binary) {
      if (preg_match(';^/nix/;', $binary)) {
        $parens['nix'] = 1;
        unset($parens['other']);
      }
      if (preg_match(';/homebrew/;', $binary)) {
        // Newer deployments use /opt/homebrew. Dunno how to check older deployments in /usr/local.
        $parens['homebrew'] = 1;
        unset($parens['other']);
      }
      if (preg_match(';MAMP;', $binary)) {
        $parens['mamp'] = 1;
        unset($parens['other']);
      }
      if (preg_match(';^/usr/bin/;', $binary)) {
        $parens['usr-bin'] = 1;
        unset($parens['other']);
      }
    }

    return sprintf('%s (%s)', PHP_VERSION, implode(', ', array_keys($parens)));
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

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return array
   */
  protected function findPathsUrls(OutputInterface $output): array {
    $error = method_exists($output, 'getErrorOutput') ? $output->getErrorOutput() : $output;
    $pathList = $urlList = [];
    $paths = \Civi::paths();

    // These are paths that a sysadmin is likely to need to consult while debugging common problems.
    $pathVariables = ['cms.root', 'civicrm.root', 'civicrm.log', 'civicrm.l10n'];
    $urlVariables = [];
    // In default (non-verbose) mode, we don't automatically print most URLs because
    // most URL-detection is HTTP-dependent. Interpreting that data takes more effort/attention.

    if ($output->isVerbose()) {
      $allVariables = property_exists($paths, 'variableFactory') ? Invasive::get([$paths, 'variableFactory']) : NULL;
      if (empty($allVariables)) {
        $error->writeln('<error>Failed to inspect Civi::paths()->variableFactory</error>');
      }
      else {
        $pathVariables = $urlVariables = array_keys($allVariables);
      }
    }

    foreach ($urlVariables as $variable) {
      try {
        $urlList['url: [' . $variable . ']'] = $paths->getUrl('[' . $variable . ']/.');
      }
      catch (\Throwable $e) {
      }
    }
    foreach ($pathVariables as $variable) {
      try {
        $pathList['path: [' . $variable . ']'] = $paths->getPath('[' . $variable . ']/.');
      }
      catch (\Throwable $e) {
      }
    }

    // Oddballs
    $urlList['url: CIVICRM_UF_BASEURL'] = \CRM_Utils_Constant::value('CIVICRM_UF_BASEURL');
    $pathList['path: extensionsDir'] = \CRM_Core_Config::singleton()->extensionsDir;

    asort($pathList);
    asort($urlList);
    return array_merge($pathList, $urlList);
  }

}
