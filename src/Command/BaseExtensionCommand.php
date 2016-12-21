<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseExtensionCommand extends BaseCommand {

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
