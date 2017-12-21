<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BaseExtensionCommand extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;

  /**
   * Register CLI options for filtering the extension feed, such as "--dev" or "--filter-ver".
   */
  public function configureRepoOptions() {
    $this
      ->addOption('dev', NULL, InputOption::VALUE_NONE, 'Include developmental extensions. (Equivalent to "--filter-status=* --filter-ready=*")')
      ->addOption('filter-ver', NULL, InputOption::VALUE_REQUIRED, 'Filter remote extensions by Civi compatibility (Ex: "4.7.15","4.6.20")', '{ver}')
      ->addOption('filter-uf', NULL, InputOption::VALUE_REQUIRED, 'Filter remote extensions by CMS compatibility (Ex: "Drupal", "WordPress")', '{uf}')
      ->addOption('filter-status', NULL, InputOption::VALUE_REQUIRED, 'Filter remote extensions by stability flag (Ex: "stable", "*")', 'stable')
      ->addOption('filter-ready', NULL, InputOption::VALUE_REQUIRED, 'Filter remote extensions based on reviewers\' approval (Ex: "ready", "*")', 'ready');
  }

  /**
   * Create a URL for the extension feed (based on user options).
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return string|NULL
   */
  public function parseRepoUrl(InputInterface $input) {
    if ($input->getOption('dev')) {
      $input->setOption('filter-status', '*');
      $input->setOption('filter-ready', '*');
    }
    $parts = array();
    foreach (array('ver', 'uf', 'status', 'ready') as $key) {
      $value = $input->getOption("filter-" . $key);
      if ($value === '*') {
        $value = '';
      }
      $parts[] = $key . '=' . $value;
    }
    return 'https://civicrm.org/extdir/' . implode('|', $parts);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   *   Array(0=>$keys, 1=>$errors).
   */
  protected function parseKeys(InputInterface $input, OutputInterface $output) {
    $allKeys = \CRM_Extension_System::singleton()->getFullContainer()->getKeys();
    $foundKeys = array();
    $missingKeys = array();
    $shortMap = NULL;

    foreach ($input->getArgument('key-or-name') as $keyOrName) {
      if (strpos($keyOrName, '.') !== FALSE) {
        if (in_array($keyOrName, $allKeys)) {
          $foundKeys[] = $keyOrName;
        }
        else {
          $missingKeys[] = $keyOrName;
        }
      }
      else {
        if ($shortMap === NULL) {
          $shortMap = $this->getShortMap();
        }
        if ($shortMap[$keyOrName]) {
          $foundKeys = array_merge($foundKeys, $shortMap[$keyOrName]);
        }
        else {
          $missingKeys[] = $keyOrName;
        }
      }
    }

    return array($foundKeys, $missingKeys);
  }

  /**
   * @return array
   *   Array(string $shortName => string $longName).
   */
  protected function getShortMap() {
    $map = array();
    $mapper = \CRM_Extension_System::singleton()->getMapper();
    $container = \CRM_Extension_System::singleton()->getFullContainer();

    foreach ($container->getKeys() as $key) {
      $info = $mapper->keyToInfo($key);
      if ($info->file) {
        $map[$info->file][] = $key;
      }
    }
    return $map;
  }

}
