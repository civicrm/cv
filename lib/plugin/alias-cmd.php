<?php

/**
 * This plugin is a supplement for basic-alias. It adds CLI commands for managing the alias-files.
 */

// Plugin lives in a unique namespace
namespace Civi\Cv\AliasCmdPlugin;

use Civi\Cv\BasicAliasPlugin\AliasFinder;

use Civi\Cv\Command\CvCommand;
use Civi\Cv\Cv;
use Civi\Cv\Util\Filesystem;
use Civi\Cv\Util\StructuredOutputTrait;
use CvDeps\Symfony\Component\Console\Input\InputArgument;
use CvDeps\Symfony\Component\Console\Output\OutputInterface;

if (empty($CV_PLUGIN['protocol']) || $CV_PLUGIN['protocol'] > 1) {
  die("Expect CV_PLUGIN API v1");
}

Cv::dispatcher()->addListener('cv.app.commands', function($e) {
  $e['commands'][] = new AliasListCommand();
  $e['commands'][] = new AliasAddCommand();
});

class AliasListCommand extends CvCommand {
  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('alias:list')
      ->setDescription('List any @aliases')
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'table', 'defaultColumns' => 'name,type,config', 'shortcuts' => TRUE])
      ->setBootOptions(['auto' => FALSE]);
  }

  protected function execute($input, $output): int {
    $aliasEvent = Cv::filter(Cv::app()->getName() . ".app.site-alias.list", [
      'aliases' => [],
    ]);
    $aliases = array_map(function($alias) {
      return [
        'name' => $alias['name'],
        'type' => $alias['type'],
        'config' => $alias['config'],
      ];
    }, $aliasEvent['aliases']);
    $this->sendStandardTable($aliases);
    return 0;
  }

}

class AliasAddCommand extends CvCommand {

  protected function configure() {
    $this
      ->setName('alias:add')
      ->addArgument('name', InputArgument::OPTIONAL, 'Alias name')
      ->addArgument('local-path', InputArgument::OPTIONAL, 'Local path to the instance (web-root)')
      ->setDescription('Interactively create a new @alias')
      ->setBootOptions(['auto' => FALSE]);
  }

  protected function execute($input, $output): int {
    if (!class_exists(AliasFinder::class)) {
      throw new \Exception("Cannot add new aliases without the \"basic-alias\" plugin.");
    }
    Cv::io()->title('Site Aliases: Add new');

    $answers['name'] = $this->askName();
    if (empty($input->getArgument('local-path'))) {
      $answers['remote_command'] = $this->askRemoteCommand();
      if ($answers['remote_command']) {
        $answers['cv_command'] = $this->askCvCommand();
        // NOTE: We don't need cv_command locally -- because clearly the user is already able to run 'cv alias:add'.
      }
    }
    $answers['path'] = $this->askPath(empty($answers['remote_command']));
    $answers['mode'] = $this->askBootstrap($answers['path']);
    $answers['settings'] = ($answers['mode'] === 'settings') ? $this->askSettings($answers['path']) : NULL;
    if ($answers['mode'] !== 'settings' && $this->askMultisite($answers['path'])) {
      $answers['url'] = $this->askUrl($answers['name'], $answers['path']);
    }
    $answers['user'] = $this->askUser($answers['name']);

    Cv::io()->section('Generate configuration');
    $configJson = $this->createInfo($answers);
    Cv::io()->writeln("<info>This is the configuration for your alias:</info>\n");
    Cv::io()->writeln($configJson, OutputInterface::OUTPUT_PLAIN);

    $this->writeInfo($this->askAliasFile($answers['name'] . '.json'), $configJson);

    Cv::io()->success([
      "Created alias \"@{$answers['name']}\".",
      "You may now run commands like \"cv @{$answers['name']} status\" ",
    ]);
    return 0;
  }

  protected function writeInfo(string $outputFile, string $infoJson): void {
    Cv::io()->section('Write ' . $outputFile);

    $parent = dirname($outputFile);
    if (!is_dir($parent)) {
      mkdir($parent, 0777, TRUE);
    }
    file_put_contents($outputFile, $infoJson);
  }

  protected function createInfo(array $answers): string {
    extract($answers);

    $info = [];

    if (!empty($answers['remote_command'])) {
      $info['remote_command'] = $answers['remote_command'];
      $info['options']['cwd'] = $answers['path'];
    }
    if (!empty($answers['cv_command'])) {
      $info['cv_command'] = $answers['cv_command'];
    }

    $modeMap = [
      'auto' => 'Auto://',
      'standalone' => 'Standalone://',
      'backdrop' => 'Backdrop://',
      'drupal' => 'Drupal8://',
      'drupal7' => 'Drupal://',
      'joomla' => 'Joomla://',
      'wordpress' => 'WordPress://',
    ];
    if (isset($modeMap[$answers['mode']])) {
      $info['env']['CIVICRM_BOOT'] = $modeMap[$answers['mode']] . ltrim($answers['path'], '/' . DIRECTORY_SEPARATOR);
    }
    elseif ($answers['mode'] === 'settings') {
      $info['env']['CIVICRM_SETTINGS'] = $answers['settings'];
      $info['options']['cwd'] = $answers['path'];
    }

    if (!empty($answers['url'])) {
      $info['options']['url'] = $answers['url'];
    }
    if (!empty($answers['user'])) {
      $info['options']['user'] = $answers['user'];
    }

    return json_encode($info, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
  }

  protected function askAliasFile(string $entry): string {
    Cv::io()->section('Identify alias storage');

    $collections['all'] = AliasFinder::getFolders();
    $collections['extant'] = array_filter($collections['all'], function($d) {
      return is_dir($d) && is_writable($d);
    });
    $collections['viable'] = array_values(array_filter($collections['all'], function($d) {
      return Filesystem::isCreatable($d);
    }));

    foreach (['extant', 'viable'] as $type) {
      $options = array_values($collections[$type]);
      foreach ($options as $option) {
        if (file_exists("$option/$entry")) {
          Cv::io()->info("Found existing file: $option/$entry");
          if (!Cv::io()->confirm('Overwrite file?')) {
            throw new \Exception("Aborted. File already exists.");
          }
          return "$option/$entry";
        }
      }
      if (count($options) === 1) {
        Cv::io()->info("Alias folder is {$options[0]}");
        return $options[0] . "/$entry";
      }
      if (count($options) > 1) {
        return Cv::io()->choice('Where should we store the alias entry?', $options) . "/$entry";
      }
    }
    throw new \RuntimeException(sprintf("Failed to identify a viable folder for aliases. All candidates (%s) are unwritable.", implode(' ', $collections['all'])));
  }

  protected function askName(): string {
    $validateName = function ($name): ?string {
      if (empty($name)) {
        throw new \Exception("The alias name is required");
      }
      $name = trim($name);
      if (!preg_match('/^[a-zA-Z0-9\-]+$/', $name)) {
        throw new \Exception("Malformed alias ($name). Use only alphanumerics and dashes.");
      }
      return $name;
    };

    if ($name = Cv::input()->getArgument('name')) {
      $name = $validateName($name);
      Cv::io()->writeln("<info>Alias-name</info>: $name");
    }
    else {
      Cv::io()->section('Configure alias-name');
      Cv::io()->info([
        'The alias is a brief nickname to identify your CiviCRM instance. It allows you to construct shorter commands.',
        "Example: Run a command with alias \"wombat\"\n$ cv @wombat status",
      ]);
      $name = Cv::io()->ask('Alias-name (required)', NULL, $validateName);
    }
    return $name;
  }

  protected function askPath(bool $isLocal): string {
    $validatePath = function ($path) use ($isLocal): ?string {
      $path = trim($path);
      $path = rtrim($path, '/' . DIRECTORY_SEPARATOR);
      if ($isLocal && !is_dir($path)) {
        throw new \Exception("The path ($path) is not valid.");
      }
      return $path;
    };

    if ($path = Cv::input()->getArgument('local-path')) {
      $path = $validatePath($path);
      Cv::io()->writeln("<info>Root-path</info>: $path");
    }
    else {
      Cv::io()->section('Configure root-path');
      Cv::io()->info('The root-path identifies the source-tree (web-root) that includes CiviCRM.');
      $path = Cv::io()->ask('Root-path (required)', getcwd(), $validatePath);
    }
    return $path;
  }

  protected function askRemoteCommand(): ?string {
    $validateCommand = function ($command): ?string {
      if (is_string($command)) {
        $command = trim($command);
        // The examples use '$ ' prefix, which may easily be misinterpreted by reader as literal.
        if (substr($command, 0, 2) === '$ ') {
          $command = substr($command, 2);
        }
      }
      if (empty($command)) {
        throw new \Exception("The command is required for remote access.");
      }
      return $command;
    };

    Cv::io()->section('Configure local/remote access');
    Cv::io()->info([
      'cv can access an instance of CiviCRM on the local host -- or on a remote (SSH) server.',
    ]);
    $isRemote = Cv::io()->choice('Where is CiviCRM running?', [
      'local' => 'Local CiviCRM site',
      'remote' => 'Remote CiviCRM site (SSH)',
    ], 'local');
    if ($isRemote === 'local') {
      return NULL;
    }
    Cv::io()->info([
      'Please describe a command to connect to the remote server. Here are a few examples.',
      "Connect via SSH to server.example.com:\n$ ssh server.example.com",
      "Connect via SSH to server.example.com as user www-data:\n$ ssh www-data@server.example.com",
      "Connect via SSH to server.example.com on port 2222:\n$ ssh -p 2222 server.example.com",
    ]);
    return Cv::io()->ask('Remote access command (required)', NULL, $validateCommand);
  }

  protected function askCvCommand(): ?string {
    // Cv::io()->section('Configure remote cv command? (optional)');
    Cv::io()->info([
      'The remote server must have its own copy of cv. If this has been installed in a standard PATH (such as /usr/local/bin), then no extra work is required.',
      'If the remote copy of cv lives in a custom location (as /var/www/mysite/vendor/bin), then specify it.',
    ]);
    return Cv::io()->ask('Remote cv command (optional)');
  }

  protected function askBootstrap(string $path): string {
    Cv::io()->section('Configure application type');
    Cv::io()->info([
      "CiviCRM may run as a standalone application or as an add-on (alongside Drupal, WordPress, or similar).",
      "cv can usually identify the application automatically by examining the file-layout.",
      "However, if you have customized the file-layout (symlinks or path-overrides), then it may need extra hints.",
    ]);
    $choice = Cv::io()->choice('Application type', [
      'auto' => 'Identify the application automatically (examine file-layout)',
      'manual' => 'Manually specify the application.',
      'settings' => 'Identify the application by reading "civicrm.settings.php" (legacy)',
      // The 'auto' and 'manual' options are more representative of HTTP lifecycle, and they can preserve current CWD.
      // However, 'settings' is closer to the actual default behavior.
    ], 'auto');

    if ($choice !== 'manual') {
      return $choice;
    }
    else {
      return Cv::io()->choice('Application type (manual)', [
        'standalone' => 'CiviCRM-Standalone',
        'backdrop' => 'Backdrop with CiviCRM',
        'drupal' => 'Drupal (8/9/10/11) with CiviCRM',
        'drupal7' => 'Drupal (7) with CiviCRM',
        'joomla' => 'Joomla with CiviCRM',
        'wordpress' => 'WordPress with CiviCRM',
      ]);
    }
  }

  protected function askMultisite(string $path): bool {
    Cv::io()->section('Configure multi-site options');
    Cv::io()->info([
      // 'In single-site installations, CiviCRM has one codebase, one database, and one URL.',
      'In multi-site installations, the codebase is shared by multiple URLs and/or multiple databases.',
      'If your system uses multi-site, then we should configure additional options.',
    ]);
    return Cv::io()->confirm("Enable multi-site options?", FALSE);
  }

  protected function askSettings(string $path): string {
    $validateSettings = function ($settings): ?string {
      $settings = trim($settings);
      if (empty($settings)) {
        throw new \Exception("The settings path is required");
      }
      if (!file_exists($settings) || !is_readable($settings)) {
        throw new \Exception("The settings path ($settings) is not valid.");
      }
      return $settings;
    };

    Cv::io()->section("Configure settings path");

    Cv::io()->info("To use this bootstrap option, we must choose a settings file. Let's search for some candidates.");
    Cv::io()->writeln('Searching...');

    $settingsFiles = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
    foreach ($iterator as $file) {
      if ($file->getFilename() === 'civicrm.settings.php') {
        $settingsFiles[] = $file->getPathname();
      }
    }
    switch (count($settingsFiles)) {
      case 0:
        throw new \Exception(sprintf('In "%s", we could not find any files named "%s". This may indicate an incorrect path or other misconfiguration.', $path, 'civicrm.settings.php'));

      case 1:
        Cv::io()->info('We found 1 file which appears to be suitable:');
        Cv::io()->listing($settingsFiles);
        return $settingsFiles[0];

      default:
        sort($settingsFiles);
        Cv::io()->info('We found ' . count($settingsFiles) . ' files which appear to be suitable:');
        Cv::io()->listing($settingsFiles);
        return Cv::io()->ask('Settings file', NULL, $validateSettings);
    }
  }

  protected function askUrl(string $name, string $path): string {
    $validateUrl = function($url): ?string {
      $url = trim($url);
      if (empty($url)) {
        throw new \Exception("The URL is required for multi-site mode.");
      }

      $parsed = parse_url($url);
      if (empty($parsed['scheme']) || empty($parsed['host'])) {
        throw new \Exception("The URL must specify a scheme and hostname, such as \"https://example.com\"");
      }
      if (!in_array($parsed['scheme'], ['http', 'https'])) {
        throw new \Exception("The only supported URL schemes are \"http\" and \"https\"");
      }
      return $url;
    };
    Cv::io()->section('Configure multi-site options: Web URL');
    Cv::io()->info([
      "Each CiviCRM instance can be identified by its web URL. Which URL should be associated with \"@{$name}\"?",
      "Example: https://sub-site-123.example.com/",
    ]);
    return Cv::io()->ask('Web URL', NULL, $validateUrl);
  }

  protected function askUser(string $name): ?string {
    Cv::io()->section('Configure default username');
    Cv::io()->info([
      "Most cv subcommands execute with super-privileges, but some require a user.",
      "If you have an existing CiviCRM user-account, you may use it by default.",
    ]);
    $user = Cv::io()->ask('Default username (optional)');
    return $user ? trim($user) : $user;
  }

}
