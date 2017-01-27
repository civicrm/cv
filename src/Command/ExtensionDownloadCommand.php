<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\ExtensionUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ExtensionDownloadCommand extends BaseExtensionCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:download')
      ->setAliases(array('dl'))
      ->setDescription('Download and enable an extension')
      ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Refresh the remote list of extensions (Default: Only refresh on cache-miss)')
      ->addArgument('key-or-name', InputArgument::IS_ARRAY, 'One or more extensions to enable. Identify the extension by full key ("org.example.foobar") or short name ("foobar"). Optionally append a URL.')
      ->setHelp('Download and enable an extension

Examples:
  cv ext:download org.example.foobar
  cv dl foobar
  cv dl --dev foobar
  cv dl "org.example.foobar@http://example.org/files/foobar.zip"

Note:
  Short names ("foobar") do not work when passing an explicit URL.

  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.

  This subcommand does not output parseable data. For parseable output,
  consider using `cv api extension.install`.
');
    parent::configureRepoOptions();
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($extRepoUrl = $this->parseRepoUrl($input)) {
      global $civicrm_setting;
      $civicrm_setting['Extension Preferences']['ext_repo_url'] = $extRepoUrl;
    }

    $this->boot($input, $output);

    $output->writeln("<info>Using extension feed \"" . \CRM_Extension_System::singleton()->getBrowser()->getRepositoryUrl() . "\"</info>");

    // Refresh extensions if (a) ---refresh enabled or (b) there's a cache-miss.
    $refresh = $input->getOption('refresh') ? 'yes' : 'auto';
    while (TRUE) {
      if ($refresh === 'yes') {
        $output->writeln("<info>Refreshing extension cache</info>");
        $result = $this->callApiSuccess($input, $output, 'Extension', 'refresh', array(
          'local' => FALSE,
          'remote' => TRUE,
        ));
        if (!empty($result['is_error'])) {
          return 1;
        }
      }

      list ($downloads, $errors) = $this->parseDownloads($input);
      if ($refresh == 'auto' && !empty($errors)) {
        $output->writeln("<info>Extension cache does contain requested item(s)</info>");
        $refresh = 'yes';
      }
      else {
        break;
      }
    }

    if (!empty($errors)) {
      foreach ($errors as $error) {
        $output->getErrorOutput()->writeln("<error>$error</error>");
      }
      $output->getErrorOutput()->writeln("<comment>Tip: To customize the feed, review options in \"cv {$input->getFirstArgument()} --help\"");
      $output->getErrorOutput()->writeln("<comment>Tip: To browse available downloads, run \"cv ext:list -R\"</comment>");
      return 1;
    }

    foreach ($downloads as $key => $url) {
      $output->writeln("<info>Downloading extension \"$key\" ($url)</info>");
      $result = $this->callApiSuccess($input, $output, 'Extension', 'download', array(
        'key' => $key,
        'url' => $url,
      ));
      if (!empty($result['is_error'])) {
        return 1;
      }
    }

    return 0;
  }

  /**
   * Get a list of all available extensions.
   *
   * @return array
   *   ($key => CRM_Extension_Info)
   */
  protected function getRemoteInfos() {
    static $cache = NULL;
    if ($cache === NULL) {
      $cache = \CRM_Extension_System::singleton()
        ->getBrowser()->getExtensions();
    }
    return $cache;
  }

  /**
   * @return array
   *   Array(string $shortName => string $longName).
   */
  protected function getRemoteShortMap() {
    static $cache = NULL;
    if ($cache === NULL) {
      $cache = array();
      foreach ($this->getRemoteInfos() as $key => $info) {
        if ($info->file) {
          $cache[$info->file][] = $key;
        }
      }
    }
    return $cache;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   *   Array(array $downloads, array $errors).
   */
  protected function parseDownloads(InputInterface $input) {
    $downloads = array(); // Array(string $key => null|string $url)
    $errors = array(); // Array(string $message).

    $remoteInfos = NULL;
    $shortMap = NULL;

    if (!$input->getArgument('key-or-name')) {
      $errors[] = 'Error: Please specify at least one extension to download';
    }

    foreach ($input->getArgument('key-or-name') as $keyOrName) {
      $url = NULL;
      if (strpos($keyOrName, '@') !== FALSE) {
        list ($keyOrName, $url) = explode('@', $keyOrName, 2);
      }

      if (strpos($keyOrName, '.') === FALSE) {
        if ($shortMap === NULL) {
          $shortMap = $this->getRemoteShortMap();
        }
        if (isset($shortMap[$keyOrName])) {
          if (count($shortMap[$keyOrName]) === 1) {
            $keyOrName = $shortMap[$keyOrName][0];
          }
          else {
            $otherNames = '"' . implode('", "', $shortMap[$keyOrName]) . '"';
            $errors[] = "Ambiguous name \"$keyOrName\". Use a more specific key: $otherNames";
            continue;
          }
        }
      }

      if (empty($url)) {
        if ($remoteInfos === NULL) {
          $remoteInfos = $this->getRemoteInfos();
        }

        if (!empty($remoteInfos[$keyOrName]->downloadUrl)) {
          $url = $remoteInfos[$keyOrName]->downloadUrl;
        }
        else {
          $errors[] = "Error: Unrecognized extension \"$keyOrName\"";
          continue;
        }
      }

      $downloads[$keyOrName] = $url;
    }
    return array($downloads, $errors);
  }

}
