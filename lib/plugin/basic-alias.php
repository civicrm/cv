<?php

/**
 * This plugin adds support for site-aliases in CiviCRM's `cv` tool.
 *
 * == INSTALLATION ==
 *
 * Put this file in ~/.cv/plugin/ or /etc/cv/plugin
 *
 * == USAGE ==
 *
 * In ~/.cv/alias/, create a file MYSITE.yaml. If your system doesn't support php-yaml, use JSON.
 *
 * == EXAMPLE FILE ==
 *
 * ```yaml
 * ## Connect to a remote server
 * remote_command: ssh webuser@server.com
 *
 * ## Use a specific copy of cv
 * cv_command: /usr/local/bin/cv
 *
 * ## Set some environment variables
 * env:
 *   CIVICRM_BOOT: "WordPress://srv/www/wpmaster/web"
 *   HTTP_HOST: "example.com"
 *
 * ## Pass some extra options like `--user=admin`
 * options:
 *   user: "admin"
 * ```
 */

// Plugin lives in a unique namespace
namespace Civi\Cv\BasicAliasPlugin;

use Civi\Cv\Cv;
use Civi\Cv\CvEvent;
use CvDeps\Symfony\Component\Console\Output\OutputInterface;

if (empty($CV_PLUGIN['protocol']) || $CV_PLUGIN['protocol'] > 1) {
  die("Expect CV_PLUGIN API v1");
}

Cv::dispatcher()->addListener('*.app.site-alias', function(CvEvent $event) {
  foreach (AliasFinder::find($event['alias']) as $file) {
    $config = AliasFinder::read($file);
    ShellAliasHandler::setup($event, $config);
  }
});

Cv::dispatcher()->addListener('*.app.site-alias.list', function(CvEvent $event) {
  foreach (AliasFinder::find('*') as $file) {
    $name = preg_replace('/\.(json|yaml)/', '', basename($file));
    $event['aliases'][] = [
      'name' => $name,
      'type' => 'basic',
      'config' => $file,
      'getter' => function () use ($file) {
        return AliasFinder::read($file);
      },
    ];
  }
});

/**
 * Find and read alias configurations.
 */
class AliasFinder {

  public static function find(string $nameOrWildcard): iterable {
    yield from [];
    foreach (static::getFolders() as $dir) {
      foreach (['yaml', 'json'] as $type) {
        $pat = "$dir/$nameOrWildcard.$type";
        $files = (array) glob($pat);
        foreach ($files as $file) {
          yield $file;
        }
      }
    }
  }

  public static function getFolders(): array {
    $dirs = ['/etc/cv/alias', '/usr/local/share/cv/alias', '/usr/share/cv/alias'];
    if (getenv('HOME')) {
      array_unshift($dirs, getenv('HOME') . '/.cv/alias');
    }
    elseif (getenv('USERPROFILE')) {
      array_unshift($dirs, getenv('USERPROFILE') . '/.cv/alias');
    }
    if (getenv('XDG_CONFIG_HOME')) {
      array_unshift($dirs, getenv('XDG_CONFIG_HOME') . '/cv/alias');
    }
    return $dirs;
  }

  public static function read(string $file): array {
    if (preg_match(';\.ya?ml$;', $file)) {
      if (!is_callable('yaml_parse')) {
        throw new \RuntimeException("Cannot load $file. Missing yaml_parse().");
      }
      $parsed = yaml_parse(file_get_contents($file));
    }
    elseif (preg_match(';\.json$;', $file)) {
      $parsed = json_decode(file_get_contents($file), 1);
    }
    else {
      throw new \RuntimeException("Unrecognized alias file type: $file");
    }

    if (empty($parsed) || !is_array($parsed)) {
      throw new \RuntimeException("Alias file ($file) appears invalid");
    }
    return $parsed;
  }

}

class ShellAliasHandler {

  /**
   * Read the configuration options from JSON/YAML. Update the $event['transport'].
   */
  public static function setup(CvEvent $event, array $config): void {
    /** @var \Civi\Cv\Util\CvArgvInput $input */
    $input = $event['input'];
    /** @var \CvDeps\Symfony\Component\Console\Output\OutputInterface $output */
    $output = $event['output'];
    $isRemote = !empty($config['remote_command']);
    $localCvBin = $input->getOriginalArgv()[0];

    $defaultConfig = [
      'env' => [],
      'options' => [],
      'cv_command' => $isRemote ? 'cv' : $localCvBin,
    ];
    $config = array_merge($defaultConfig, $config);

    $cvCommand = array_merge(static::cvCommand($config), static::passthruArgs($input->getOriginalArgv()));
    if ($isRemote) {
      $fullCommand = $config['remote_command'] . ' bash -c ' . escapeshellarg(implode(' ', $cvCommand));
    }
    else {
      $fullCommand = '( ' . implode(' ', $cvCommand) . ' )';
    }

    $event['transport'] = function() use ($input, $output, $fullCommand) {
      if ($output->isVeryVerbose()) {
        $output->write('<info>Found alias. Run subcommand:</info> ');
        $output->writeln($fullCommand, OutputInterface::OUTPUT_RAW);
      }
      // echo "TODO call passthru\n";
      static::passthru($fullCommand);
    };
  }

  public static function cvCommand(array $config): array {
    $result = [];
    if (!empty($config['env'])) {
      foreach ($config['env'] as $key => $value) {
        $result[] = "$key=" . escapeString($value);
      }
      // This technique allows things like "
      $result[] = sprintf('; export %s;', implode(' ', array_keys($config['env'])));
    }

    $result[] = $config['cv_command'];
    foreach ($config['options'] ?? [] as $key => $value) {
      if ($value === NULL) {
        $result[] = escapeString("--$key");
      }
      else {
        $result[] = escapeString("--$key=$value");
      }
    }

    return $result;
  }

  /**
   * Figure out which arguments to pass-thru to subcommand.
   *
   * @param array $rawArgs
   * @return array
   */
  public static function passthruArgs(array $rawArgs): array {
    array_shift($rawArgs); /* ignore program name */

    $result = [];
    while (count($rawArgs)) {
      $rawArg = array_shift($rawArgs);
      if ($rawArg === '--site-alias') {
        // Ignore next part
        array_shift($rawArgs);
      }
      elseif (strpos($rawArg, '--site-alias=') === 0) {
        // ignore
      }
      else {
        $result[] = escapeString($rawArg);
      }
    }
    return $result;
  }

  public static function passthru(string $command): int {
    $process = proc_open(
      $command,
      [0 => STDIN, 1 => STDOUT, 2 => STDERR],
      $pipes
    );
    return proc_close($process);
  }

}

function escapeString(string $expr): string {
  return preg_match('{^[\w=-]+$}', $expr) ? $expr : escapeshellarg($expr);
}
