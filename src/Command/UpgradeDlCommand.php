<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Command for asking CiviCRM for the appropriate tarball to download.
 */
class UpgradeDlCommand extends BaseCommand {
  protected function configure() {
    $this
      ->setName('upgrade:dl')
      ->setDescription('Download CiviCRM code and put it in place for an upgrade')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat())
      ->addOption('stability', 's', InputOption::VALUE_REQUIRED, 'Specify the stability of the version to get (beta, rc, stable)', 'stable')
      ->addOption('cms', 'c', InputOption::VALUE_REQUIRED, 'Specify the cms to get (Backdrop, Drupal, Drupal6, Joomla, Wordpress) instead of the current site')
      ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'Specify the URL to a tarball/zipfile for downloading (regardless of --stability and --cms)')
      ->addOption('temploc', NULL, InputOption::VALUE_REQUIRED, 'Specify the location to put the temporary tarball', '/tmp')
      ->setHelp('Download CiviCRM code and put it in place for an upgrade

Examples:
  cv upgrade:dl --stability=rc

Returns the revision number
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
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
    $ch = curl_init($url);
    $parts = explode('/', $url);
    $filename = array_pop($parts);
    $temp = fopen("$temploc/$filename", "w");
    curl_setopt($ch, CURLOPT_FILE, $temp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    // Extract the tarball / zipfile to the temp folder
    $parts = explode('.', $filename);
    $suffix = array_pop($parts);
    $tail = empty($dl['rev']) ? time() : $dl['rev'];
    $foldername = array_shift($parts) . $tail;
    if ($suffix == 'zip') {
      $command = "unzip $temploc/$filename -d $temploc/$foldername";
    }
    else {
      $command = "tar -xzf $temploc/$filename -C $temploc/$foldername";
    }
    $p = new Process("mkdir -p $temploc/$foldername && $command");
    $p->run();
    if (!$p->isSuccessful()) {
      throw new ProcessFailedException($p);
    }

    // Rsync the files into place
    $dest = $vars['CIVI_FILES'];
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

        // Files that should be preserved on the site
        $excludeFiles = array(
          'civicrm.settings.php',
          'settings_location.php',
        );

        $command = 'rsync -rl --delete-after';
        foreach ($excludeFiles as $x) {
          $command .= " --exclude $x";
        }

        $p = new Process("$commmand $temploc/$foldername/civicrm/ $dest");
        $p->run();
        if (!$p->isSuccessful()) {
          throw new ProcessFailedException($p);
        }
        break;

      case 'Joomla':
        // https://www.joomlatools.com/developer/tools/console/commands/extension/#extensioninstallfile
        break;
    }

    $result = 'upgraded';

    $this->sendResult($input, $output, $result);
  }

}
