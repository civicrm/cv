<?php
namespace Civi\Cv\Util;

use ZipArchive;

class HeadlessDownloader {

  /**
   * Download and extract an extension.
   *
   * @param string $zipUrl
   *   The full URL of the extension zip file.
   *   Ex: "https://github.com/foo/bar/archive/master.zip".
   * @param string $extKey
   *   Ex: "org.example.foobar"
   * @param string $outputDir
   *   Ex: "/var/www/sites/default/ext/org.example.foobar".
   * @param bool $force
   *   If $outputDir already exists, delete and replace it.
   */
  public function run($zipUrl, $extKey, $outputDir, $force) {
    $fs = new Filesystem();

    if ($fs->exists($outputDir) && !$force) {
      throw new \RuntimeException("Directory already exists: $outputDir");
    }

    $tmpDir = $outputDir . ".tmp";
    if ($fs->exists($tmpDir) && !$force) {
      throw new \RuntimeException("Directory already exists: $tmpDir");
    }

    $zipFile = tempnam(sys_get_temp_dir(), 'extdl-') . '.zip';
    if ($fs->exists($tmpDir) && $force) {
      $fs->remove($tmpDir);
    }
    $this->download($zipUrl, $zipFile);
    $extractedZipPath = $this->extractZip($zipFile, $extKey, $tmpDir);
    if ($fs->exists($outputDir) && $force) {
      $fs->remove($outputDir);
    }
    rename($extractedZipPath, $outputDir);
    rmdir($tmpDir);
    unlink($zipFile);
  }

  /**
   * Determine the name.
   *
   * @param \ZipArchive $zip
   * @return array
   */
  public function findBaseDirs(ZipArchive $zip) {
    $cnt = $zip->numFiles;
    $basedirs = array();

    for ($i = 0; $i < $cnt; $i++) {
      $filename = $zip->getNameIndex($i);
      // hypothetically, ./ or ../ would not be legit here
      if (preg_match('/^[^\/]+\/$/', $filename) && $filename != './' && $filename != '../') {
        $basedirs[] = rtrim($filename, '/');
      }
    }

    return $basedirs;
  }


  public function guessBasedir(ZipArchive $zip, $expected) {
    $candidate = FALSE;
    $basedirs = $this->findBaseDirs($zip);
    if (in_array($expected, $basedirs)) {
      $candidate = $expected;
    }
    elseif (count($basedirs) == 1) {
      $candidate = array_shift($basedirs);
    }
    if ($candidate !== FALSE && preg_match('/^[a-zA-Z0-9]/', $candidate)) {
      return $candidate;
    }
    else {
      return FALSE;
    }
  }

  public function extractZip($zipFile, $key, $tmpDir) {
    $zip = new ZipArchive();
    $res = $zip->open($zipFile);
    if ($res === TRUE) {
      $zipSubDir = $this->guessBasedir($zip, $key);
      if ($zipSubDir === FALSE) {
        throw new \Exception('Unable to extract the extension: bad directory structure');
      }
      $extractedZipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipSubDir;
      if (is_dir($extractedZipPath)) {
        throw new \Exception("$extractedZipPath already exists");
      }
      if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, TRUE);
      }
      if (!$zip->extractTo($tmpDir)) {
        throw new \Exception("Unable to extract the extension to $tmpDir.");
      }
      $zip->close();
      return $extractedZipPath;
    }
    else {
      throw new \Exception('Unable to extract the extension.');
    }
  }

  public function download($url, $file) {
    $fp = fopen($file, 'w');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
  }

}
