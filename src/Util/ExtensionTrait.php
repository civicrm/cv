<?php
namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait ExtensionTrait {

  /**
   * Register CLI options for filtering the extension feed, such as "--dev" or "--filter-ver".
   */
  public function configureRepoOptions() {
    $this
      ->addOption('dev', NULL, InputOption::VALUE_NONE, 'Include developmental extensions. (Equivalent to "--filter-status=* --filter-ready=*")')
      ->addOption('filter-ver', NULL, InputOption::VALUE_REQUIRED, 'Filter remote extensions by Civi compatibility (Ex: "4.7.15","4.6.20")', '{ver}')
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
    foreach (array('ver', 'status', 'ready') as $key) {
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
   * @param Symfony\Component\Console\Output\OutputInterface $output
   * @return array
   *   Array(0=>$keys, 1=>$errors).
   */
  protected function parseKeys(InputInterface $input, OutputInterface $output) {
    $allKeys = \CRM_Extension_System::singleton()->getFullContainer()->getKeys();
    $foundKeys = array();
    $missingKeys = array();
    $shortMap = NULL;

    foreach ($input->getArgument('key-or-name') as $keyOrName) {
      if (in_array($keyOrName, $allKeys)) {
        $foundKeys[] = $keyOrName;
        continue;
      }

      if ($shortMap === NULL) {
        $shortMap = $this->getShortMap();
      }
      if (isset($shortMap[$keyOrName])) {
        $foundKeys = array_merge($foundKeys, $shortMap[$keyOrName]);
        continue;
      }

      $missingKeys[] = $keyOrName;
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
