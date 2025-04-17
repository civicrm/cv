<?php
namespace Civi\Cv\ExtensionPolyfill;

use Civi;
use CRM_Core_Config;
use CRM_Core_Invoke;
use CRM_Extension_Exception;
use CRM_Extension_Info;
use CRM_Extension_System;
use CRM_Extension_Upgrades;
use CRM_Queue_TaskContext;
use CRM_Utils_File;

/**
 * Library of queue-tasks which are useful for extension-management.
 */
class PfQueueTasks {

  /**
   * Download extension ($key) from $url and store it in {$stagingPath}/new/{$key}.
   * @throws \CRM_Core_Exception
   */
  public static function fetch(CRM_Queue_TaskContext $ctx, string $stagingPath, string $key, string $url): bool {
    $tmpDir = "$stagingPath/tmp";
    $zipFile = "$stagingPath/fetch/$key.zip";
    $stageDir = "$stagingPath/new/$key";

    PfHelper::createDir($tmpDir);
    PfHelper::createDir(dirname($zipFile));
    PfHelper::createDir(dirname($stageDir));

    if (file_exists($stageDir)) {
      // In case we're retrying from a prior failure.
      CRM_Utils_File::cleanDir($stageDir, TRUE, FALSE);
    }

    $downloader = CRM_Extension_System::singleton()->getDownloader();
    if (!$downloader->fetch($url, $zipFile)) {
      throw new CRM_Extension_Exception("Failed to download: $url");
    }

    $extractedZipPath = PfHelper::extractFiles($key, $zipFile, $tmpDir);
    if (!$extractedZipPath) {
      throw new CRM_Extension_Exception("Failed to extract: $zipFile");
    }

    if (!$downloader->validateFiles($key, $extractedZipPath)) {
      throw new CRM_Extension_Exception("Failed to validate $extractedZipPath. Consult CiviCRM log for details.");
      // FIXME: Might be nice to show errors immediately, but we've got bigger fish to fry right now.
    }

    if (!rename($extractedZipPath, $stageDir)) {
      throw new CRM_Extension_Exception("Failed to rename $extractedZipPath to $stageDir");
    }

    return TRUE;
  }

  /**
   * Scan the downloaded extensions and verify that their requirements are satisfied.
   * This checks requirements as declared in the staging area.
   * @throws \CRM_Core_Exception
   */
  public static function preverify(CRM_Queue_TaskContext $ctx, string $stagingPath, array $keys): bool {
    $infos = CRM_Extension_System::singleton()->getMapper()->getAllInfos();
    foreach ($keys as $key) {
      $infos[$key] = CRM_Extension_Info::loadFromFile("$stagingPath/new/$key/" . CRM_Extension_Info::FILENAME);
    }

    $errors = PfHelper::checkInstallRequirements($keys, $infos);
    if (!empty($errors)) {
      Civi::log()->error('Failed to verify requirements for new downloads in {path}', [
        'path' => $stagingPath,
        'installKeys' => $keys,
        'errors' => $errors,
      ]);
      throw new CRM_Extension_Exception(implode("\n", array_merge(
        ["Failed to verify requirements for new downloads in $stagingPath."],
        array_column($errors, 'title'),
        ["Consult CiviCRM log for details."],
      )));
    }

    return TRUE;
  }

  /**
   * Take the extracted code (`stagingDir/new/{key}`) and put it into its final place.
   * Move any old code to the backup (`stagingDir/old/{key}`).
   * Delete the container-cache
   * @throws \CRM_Core_Exception
   */
  public static function swap(CRM_Queue_TaskContext $ctx, string $stagingPath, array $keys): bool {
    PfHelper::createDir("$stagingPath/old");
    try {
      foreach ($keys as $key) {
        $tmpCodeDir = "$stagingPath/new/$key";
        $backupCodeDir = "$stagingPath/old/$key";

        PfHelper::basicReplace($tmpCodeDir, $backupCodeDir);
        // What happens when you call replace(.., refresh: false)? Varies by type:
        // - For report/search/payment-extensions, it runs the uninstallation/reinstallation routines.
        // - For module-extensions, it swaps the folders and clears the class-index.

        // Arguably, for DownloadQueue, we should only clear class-index after all code is swapped,
        // but it's messier to write that patch, and it's not clear if it's needed.
      }
    } finally {
      // Delete `CachedCiviContainer.*.php`, `CachedExtLoader.*.php`, and similar.
      $config = CRM_Core_Config::singleton();
      $config->cleanup(1);
      CRM_Core_Config::clearDBCache();
      // $config->cleanupCaches(FALSE);
    }

    return TRUE;
  }

  /**
   * @param \CRM_Queue_TaskContext $ctx
   * @return bool
   * @throws \Exception
   */
  public static function rebuild(CRM_Queue_TaskContext $ctx): bool {
    CRM_Core_Invoke::rebuildMenuAndCaches(TRUE, FALSE);
    return TRUE;
  }

  /**
   * Scan the downloaded extensions and verify that their requirements are satisfied.
   */
  public static function enable(CRM_Queue_TaskContext $ctx, string $stagingPath, array $keys): bool {
    CRM_Extension_System::singleton()->getManager()->enable($keys);
    return TRUE;
  }

  public static function upgradeDb(CRM_Queue_TaskContext $ctx): bool {
    if (CRM_Extension_Upgrades::hasPending()) {
      CRM_Extension_Upgrades::fillQueue($ctx->queue);
    }
    return TRUE;
  }

  /**
   * @param \CRM_Queue_TaskContext $ctx
   * @param string $stagingPath
   * @return bool
   * @throws \CRM_Core_Exception
   */
  public static function cleanup(CRM_Queue_TaskContext $ctx, string $stagingPath): bool {
    CRM_Utils_File::cleanDir($stagingPath, TRUE, FALSE);
    $parent = dirname($stagingPath);
    $siblings = preg_grep('/^\.\.?$/', scandir($parent), PREG_GREP_INVERT);
    if (empty($siblings)) {
      rmdir($parent);
    }
    return TRUE;
  }

}
