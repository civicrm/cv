<?php

namespace Civi\Cv\ExtensionPolyfill;

use Civi;
use CRM_Core_Exception;
use CRM_Core_Session;
use CRM_Extension_Exception;
use CRM_Extension_Exception_MissingException;
use CRM_Extension_Info;
use CRM_Extension_Manager;
use CRM_Extension_System;
use CRM_Utils_File;
use CRM_Utils_Zip;
use ZipArchive;

class PfHelper {

  /**
   * NOTE: Older versions of File::createDir() did not support emitting exceptions. Hence this port.
   * @param string $path
   * @return void
   * @throws \CRM_Core_Exception
   * @see CRM_Utils_File::createDir()
   */
  public static function createDir(string $path) {
    if (is_dir($path)) {
      return;
    }
    if (@mkdir($path, 0777, TRUE) == FALSE) {
      throw new CRM_Core_Exception("Failed to create directory: $path");
    }
  }

  /**
   * NOTE: Older versions of extractFiles() did not support $extractTo. Hence this port.
   *
   * @param $key
   * @param $zipFile
   * @param string|null $extractTo
   *
   * @return false|string
   * @throws \CRM_Core_Exception
   * @see \CRM_Extension_Downloader::extractFiles()
   */
  public static function extractFiles($key, $zipFile, ?string $extractTo = NULL) {
    $extractTo = $extractTo ?: CRM_Extension_System::singleton()->getDownloader()->tmpDir;

    $zip = new ZipArchive();
    $res = $zip->open($zipFile);
    if ($res === TRUE) {
      $zipSubDir = CRM_Utils_Zip::guessBasedir($zip, $key);
      if ($zipSubDir === FALSE) {
        Civi::log()->error('Unable to extract the extension: bad directory structure');
        CRM_Core_Session::setStatus(ts('Unable to extract the extension: bad directory structure'), '', 'error');
        return FALSE;
      }
      $extractedZipPath = $extractTo . DIRECTORY_SEPARATOR . $zipSubDir;
      if (is_dir($extractedZipPath)) {
        if (!CRM_Utils_File::cleanDir($extractedZipPath, TRUE, FALSE)) {
          Civi::log()->error('Unable to extract the extension {extension}: {path} cannot be cleared', [
            'extension' => $key,
            'path' => $extractedZipPath,
          ]);
          CRM_Core_Session::setStatus(ts('Unable to extract the extension: %1 cannot be cleared', [1 => $extractedZipPath]), ts('Installation Error'), 'error');
          return FALSE;
        }
      }
      if (!$zip->extractTo($extractTo)) {
        Civi::log()->error('Unable to extract the extension to {path}.', ['path' => $extractTo]);
        CRM_Core_Session::setStatus(ts('Unable to extract the extension to %1.', [1 => $extractTo]), ts('Installation Error'), 'error');
        return FALSE;
      }
      $zip->close();
    }
    else {
      Civi::log()->error('Unable to extract the extension');
      CRM_Core_Session::setStatus(ts('Unable to extract the extension'), '', 'error');
      return FALSE;
    }

    return $extractedZipPath;
  }

  /**
   * NOTE: Older versions of CRM_Extension_Manager did not have this method.
   *
   * @see \CRM_Extension_Manager::checkInstallRequirements()
   */
  public static function checkInstallRequirements(array $installKeys, $newInfos = NULL): array {
    $manager = CRM_Extension_System::singleton()->getManager();
    $errors = [];
    $requiredExtensions = static::findInstallRequirements($installKeys, $newInfos);
    $installKeysSummary = implode(',', $requiredExtensions);
    foreach ($requiredExtensions as $extension) {
      if ($manager->getStatus($extension) !== CRM_Extension_Manager::STATUS_INSTALLED && !in_array($extension, $installKeys)) {
        $requiredExtensionInfo = CRM_Extension_System::singleton()->getBrowser()->getExtension($extension);
        $requiredExtensionInfoName = empty($requiredExtensionInfo->name) ? $extension : $requiredExtensionInfo->name;
        $errors[] = [
          'title' => ts('Missing Requirement: %1', [1 => $extension]),
          'message' => ts('You will not be able to install/upgrade %1 until you have installed the %2 extension.', [1 => $installKeysSummary, 2 => $requiredExtensionInfoName]),
        ];
      }
    }
    return $errors;
  }

  /**
   * NOTE: Older versions of Manager::findInstallRequirements() were less accepting of $newInfos.
   * @see \CRM_Extension_Manager::findInstallRequirements()
   */
  public static function findInstallRequirements($keys, $newInfos = NULL) {
    $mapper = CRM_Extension_System::singleton()->getMapper();
    if (is_object($newInfos)) {
      $infos[$newInfos->key] = $newInfos;
    }
    elseif (is_array($newInfos)) {
      $infos = $newInfos;
    }
    else {
      $infos = $mapper->getAllInfos();
    }
    // array(string $key).
    $todoKeys = array_unique($keys);
    // array(string $key => 1);
    $doneKeys = [];
    $sorter = Civi\Cv\Top::create('MJS\TopSort\Implementations\FixedArraySort');

    while (!empty($todoKeys)) {
      $key = array_shift($todoKeys);
      if (isset($doneKeys[$key])) {
        continue;
      }
      $doneKeys[$key] = 1;

      /** @var \CRM_Extension_Info $info */
      $info = @$infos[$key];

      if ($info && $info->requires) {
        $sorter->add($key, $info->requires);
        $todoKeys = array_merge($todoKeys, $info->requires);
      }
      else {
        $sorter->add($key, []);
      }
    }
    return $sorter->sort();
  }

  /**
   * NOTE: Older versions of Manager::replace() did not support $backupCodeDir or $refresh. Hence this port.
   *
   * Install or upgrade the code for an extension -- and perform any
   * necessary database changes (eg replacing extension metadata).
   *
   * This only works if the extension is stored in the default container.
   *
   * @param string $tmpCodeDir
   *   Path to a local directory containing a copy of the new (inert) code.
   * @param string|null $backupCodeDir
   *   Optionally move the old code to $backupCodeDir
   * @return string
   *   The final path where the extension has been loaded.
   * @throws CRM_Extension_Exception
   * @see CRM_Extension_Manager::replace()
   */
  public static function basicReplace(string $tmpCodeDir, ?string $backupCodeDir = NULL): string {
    $defaultContainer = CRM_Extension_System::singleton()->getDefaultContainer();
    $fullContainer = CRM_Extension_System::singleton()->getFullContainer();
    $manager = CRM_Extension_System::singleton()->getManager();

    if (!$defaultContainer) {
      throw new CRM_Extension_Exception("Default extension container is not configured");
    }

    $newInfo = CRM_Extension_Info::loadFromFile($tmpCodeDir . DIRECTORY_SEPARATOR . CRM_Extension_Info::FILENAME);
    if ($newInfo->type !== 'module') {
      throw new \CRM_Extension_Exception("PfHelper::replace() only supports module extensions");
    }

    $oldStatus = $manager->getStatus($newInfo->key);

    // find $tgtPath
    try {
      // We prefer to put the extension in the same place (where it already exists).
      $tgtPath = $fullContainer->getPath($newInfo->key);
    }
    catch (CRM_Extension_Exception_MissingException $e) {
      // the extension does not exist in any container; we're free to put it anywhere
      $tgtPath = $defaultContainer->getBaseDir() . DIRECTORY_SEPARATOR . $newInfo->key;
    }
    if (!CRM_Utils_File::isChildPath($defaultContainer->getBaseDir(), $tgtPath, FALSE)) {
      // But if we don't control the folder, then force installation in the default-container
      $oldPath = $tgtPath;
      $tgtPath = $defaultContainer->getBaseDir() . DIRECTORY_SEPARATOR . $newInfo->key;
      CRM_Core_Session::setStatus(ts('A copy of the extension (%1) is in a system folder (%2). The system copy will be preserved, but the new copy will be used.', [
        1 => $newInfo->key,
        2 => $oldPath,
      ]), '', 'alert', ['expires' => 0]);
    }

    if ($backupCodeDir && is_dir($tgtPath)) {
      if (!rename($tgtPath, $backupCodeDir)) {
        throw new CRM_Extension_Exception("Failed to move $tgtPath to backup $backupCodeDir");
      }
    }

    // move the code!
    if (!CRM_Utils_File::replaceDir($tmpCodeDir, $tgtPath)) {
      throw new CRM_Extension_Exception("Failed to move $tmpCodeDir to $tgtPath");
    }
    switch ($oldStatus) {
      case CRM_Extension_Manager::STATUS_INSTALLED:
      case CRM_Extension_Manager::STATUS_INSTALLED_MISSING:
      case CRM_Extension_Manager::STATUS_DISABLED:
      case CRM_Extension_Manager::STATUS_DISABLED_MISSING:
        $reflection = new \ReflectionMethod($manager, '_updateExtensionEntry');
        $reflection->setAccessible(TRUE);
        $reflection->invokeArgs($manager, [$newInfo]);

        if (class_exists('Civi\Core\ClassScanner')) {
          \Civi\Core\ClassScanner::cache('structure')->flush();
          \Civi\Core\ClassScanner::cache('index')->flush();
        }
        break;
    }

    return $tgtPath;
  }

}
