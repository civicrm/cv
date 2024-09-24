<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Command for asking CiviCRM for the appropriate tarball to download.
 */
class UpgradeDlCommand extends CvCommand {

  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('upgrade:dl')
      ->setDescription('Download CiviCRM code and put it in place for an upgrade')
      ->configureOutputOptions()
      ->addOption('stability', 's', InputOption::VALUE_REQUIRED, 'Specify the stability of the version to get (beta, rc, stable)', 'stable')
      ->addOption('cms', 'c', InputOption::VALUE_REQUIRED, 'Specify the cms to get (Backdrop, Drupal, Drupal6, Joomla, Wordpress) instead of the current site')
      ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'Specify the URL to a tarball/zipfile for downloading (regardless of --stability and --cms)')
      ->addOption('temploc', NULL, InputOption::VALUE_REQUIRED, 'Specify the location to put the temporary tarball', sys_get_temp_dir())
      ->setHelp('Download CiviCRM code and put it in place for an upgrade

Examples:
  cv upgrade:dl --stability=rc

Returns a JSON object with the properties:
  downloadedFile   The path to the downloaded archive
  extractedDir     The path to the extracted archive (not performed for Joomla)
  installedTo      The path to the `civicrm` directory of the file upgrade
');
    // parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    // Figure out the URL, whether specified or automatic
    $url = $input->getOption('url');
    if (empty($url)) {
      $stability = $input->getOption('stability');
      $command = "upgrade:get --stability=$stability";
      $cms = $input->getOption('cms');
      if (!empty($cms)) {
        $command .= " --cms=$cms";
      }
      $dl = \Civi\Cv\Util\Cv::run($command);
      if (empty($dl['url'])) {
        $error = 'No URL available for downloading';
        $error .= empty($dl['error']) ? '.' : ": {$dl['error']}";
        throw new \RuntimeException($error);
      }
      $url = $dl['url'];
    }

    // Get information for where the site should go.
    $vars = empty($dl['vars']) ? \Civi\Cv\Util\Cv::run('vars:show') : $dl['vars'];
    if (empty($cms)) {
      $cms = $vars['CIVI_UF'];
    }

    // Get the tarball/zipfile
    $temploc = $input->getOption('temploc');
    $filename = basename($url);
    $got = file_get_contents($url);
    if ($got === FALSE) {
      throw new \RuntimeException("Download of $url failed.");
    }
    file_put_contents("$temploc/$filename", $got);

    $result = array(
      'downloadedFile' => "$temploc/$filename",
    );

    // Rsync the files into place
    $dest = $vars['CIVI_CORE'];
    switch ($cms) {
      case 'WordPress':
        // Drop the final "civicrm/" from the file path, otherwise the same as Drupal
        $dest = substr($dest, 0, -8);

      case 'Backdrop':
      case 'Drupal':
      case 'Drupal6':
        // Ensure trailing slash
        if (substr($dest, -1) != '/') {
          $dest .= '/';
        }

        // Extract the archive to a temporary folder then rsync
        $tail = empty($dl['rev']) ? time() : $dl['rev'];
        $foldername = pathinfo($filename, PATHINFO_FILENAME) . $tail;
        $result['extractedDir'] = "$temploc/$foldername";
        $this->extractAndRsync("$temploc/$filename", "$temploc/$foldername", $dest);
        break;

      case 'Joomla':
        // NOTE: This requires Joomla console for upgrading CiviCRM.
        // Also, install/upgrade via Joomla console will only work properly when
        // `$live_site` is set in configuration.php.  Otherwise, Joomla console
        // won't know the site's URL.
        $www = dirname($dest);
        $sitename = basename($dest);
        $p = Process::fromShellCommandline("joomla extension:installfile --www $www $sitename $temploc/$filename");
        $p->run();
        if (!$p->isSuccessful()) {
          throw new ProcessFailedException($p);
        }
        break;
    }
    $result['installedTo'] = $dest;

    $this->sendResult($input, $output, $result);
    return 0;
  }

  /**
   * Extract an archive into a temporary folder, then rsync to a destination
   *
   * @param string $fileloc
   *   The location of the archive file.
   * @param string $folderloc
   *   The path for the extraction folder.
   * @param string $dest
   *   The folder to rsync the files into.
   */
  protected function extractAndRsync($fileloc, $folderloc, $dest) {
    // Extract the tarball / zipfile to the temp folder

    if (pathinfo($fileloc, PATHINFO_EXTENSION) == 'zip') {
      $command = "unzip $fileloc -d $folderloc";
    }
    else {
      $command = "tar -xzf $fileloc -C $folderloc";
    }
    $p = Process::fromShellCommandline("mkdir -p $folderloc && $command");
    $p->run();
    if (!$p->isSuccessful()) {
      throw new ProcessFailedException($p);
    }

    // Files that should be preserved on the site
    $excludeFiles = array(
      'civicrm.settings.php',
      'settings_location.php',
    );

    $command = 'rsync -rl --delete-after';
    foreach ($excludeFiles as $x) {
      $command .= " --exclude $x";
    }

    $p = Process::fromShellCommandline("$command $folderloc/civicrm/ $dest");
    $p->run();
    if (!$p->isSuccessful()) {
      throw new ProcessFailedException($p);
    }
  }

}
