#!/usr/bin/env pogo
<?php
#!require clippy/std: ~0.4.4
#!require clippy/container: '~1.2'
#!require pear/crypt_gpg: ~1.6.4

###############################################################################
## Bootstrap
namespace Clippy;

// use GuzzleHttp\HandlerStack;
// use Symfony\Component\Console\Input\InputInterface;
// use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

assertThat(PHP_SAPI === 'cli', "Releaser may only run via CLI");
$c = clippy()->register(plugins());

###############################################################################
## Configuration

$c['srcDir'] = fn() => realpath(dirname(pogo_script_dir()));
$c['buildDir'] = fn($srcDir) => autodir("$srcDir/build");
$c['distDir'] = fn($buildDir) => autodir("$buildDir/dist");
$c['cvlibUpstream'] = fn() => 'file:///tmp/cv-lib-upstream';
// FIXME // $c['cvlibUpstream'] = fn() => 'git@github.com:civicrm/cv-lib.git';
$c['cvlibWorkDir'] = fn($buildDir) => $buildDir . '/cv-lib';

// ###############################################################################
// ## Services / computed data
//
// /**
//  * Create a client for communicating with Github API.
//  *
//  * @param string $userRepo
//  * @return \GuzzleHttp\Client
//  */
// $c['githubClient()'] = function(string $ownerRepo, Credentials $cred, HandlerStack $guzzleHandler) {
//   assertThat(preg_match(';^\w+/\w+$;', $ownerRepo), "Project should match OWNER/REPO");
//   [$owner, $repo] = explode('/', $ownerRepo);
//   $host = 'github.com';
//
//   static $credCache = [];
//   $credCache[$host] = $credCache[$host] ?? $cred->get('GITHUB_TOKEN', $host);
//
//   $client = new \GuzzleHttp\Client([
//     'base_uri' => "https://api.github.com/repos/{$owner}%2F{$repo}/",
//     'headers' => [
//       'Authorization' => 'Bearer ' . $credCache[$host],
//       'Accept' => 'application/vnd.github.v3+json',
//     ],
//     'handler' => $guzzleHandler,
//   ]);
//   return $client;
// };
//
// /**
//  * Upload a list of files to Github. Attach them to a specific release.
//  * @param string $projectUrl
//  *   Base URL for Github project (https:///DOMAIN/OWNER/REPO).
//  * @param string $verNum
//  * @param string[] $assets
//  *   List of local files to upload. The remote file will have a matching name.
//  */
// $c['githubUpload()'] = function (string $projectUrl, string $verNum, array $assets, SymfonyStyle $io, $githubClient, $input, $githubRelease) {
//   $verbose = function($data) use ($io) {
//     return $io->isVerbose() ? toJSON($data) : '';
//   };
//
//   $client = $githubClient($projectUrl);
//   assertThat(preg_match('/^\d[0-9a-z\.\-\+]*$/', $verNum));
//   $io->writeln(sprintf("<info>Upload to project <comment>%s</comment> for version <comment>%s</comment> with files:\n<comment>  * %s</comment></info>", $projectUrl, $verNum, implode("\n  * ", $assets)));
//
//   $githubRelease($client, $verNum);
//
//   try {
//     $existingAssets = fromJSON($client->get('releases/' . urlencode($verNum) . '/assets/links'));
//     $existingAssets = index(['name'], $existingAssets);
//   }
//   catch (\Exception $e) {
//     $existingAssets = [];
//   }
//
//   foreach ($assets as $asset) {
//     assertThat(file_exists($asset), "File $asset does not exist");
//     if ($input->getOption('dry-run')) {
//       $io->note("(DRY-RUN) Skipped upload of $asset");
//       continue;
//     }
//     $upload = fromJSON($client->post('uploads', [
//       'multipart' => [
//         ['name' => 'file', 'contents' => fopen($asset, 'r')],
//       ],
//     ]));
//     $io->writeln("<info>Created new upload</info> " . $verbose($upload));
//
//     if (isset($existingAssets[basename($asset)])) {
//       $delete = fromJSON($client->delete('releases/' . urlencode($verNum) . '/assets/links/' . $existingAssets[basename($asset)]['id']));
//       $io->writeln("<info>Deleted old upload</info> " . $verbose($delete));
//       // Should we also delete the previous upload? Is that possible?
//     }
//
//     $release = fromJSON($client->post('releases/' . urlencode($verNum) . '/assets/links', [
//       'form_params' => [
//         'name' => basename($asset),
//         'url' => joinUrl($projectUrl, $upload['url']),
//       ],
//     ]));
//     $io->writeln("<info>Updated release</info> " . $verbose($release));
//   }
// };

###############################################################################
## Services and other helpers

$c['gpg'] = function(Credentials $cred): \Crypt_GPG {
  $gpg = new \Crypt_GPG(['binary' => trim(`which gpg`)]);
  $gpg->addSignKey($cred->get('GPG_KEY'), $cred->get('GPG_PASSPHRASE'));
  return $gpg;
};

$c['boxJson'] = function(string $srcDir): array {
  $file = $srcDir . '/box.json';
  assertThat(file_exists($file), "File not found: $file");
  return fromJSON(file_get_contents($file));
};

$c['boxOutputPhar'] = function($srcDir, $boxJson) {
  assertThat(!empty($boxJson['output']));
  return $srcDir . '/' . $boxJson['output'];
};

/**
 * Map 'git' subcommands to object-methods. Execute via Taskr.
 *
 * For comparison:
 *
 * Bash:
 *   git tag -f $version
 *   git push -f origin $version
 * PHP:
 *   $git()->tag('-f', $version);
 *   $git()->push('-f', 'origin', $version);
 * PHP (advanced):
 *   $git('/path/to/repo')
 *     ->tag('-f', $version)
 *     ->push('-f', 'origin', $version);
 *
 * There are no output values or return results. Errors will raise exceptions.
 *
 * @param \Clippy\Taskr $taskr
 * @return \Closure
 * @throws CmdrProcessException
 */
$c['git'] = function (Taskr $taskr) {
  return function($path = '.') use ($taskr) {
    return new ClosureObject(function($self, $cmdName, ...$args) use ($taskr, $path) {
      $taskr->passthru('cd {{0|s}} && git {{1|s}} {{2|@s}}', [$path, $cmdName, $args]);
      return $self;
    });
  };
};

/**
 * Make a directory (if needed). Return the name.
 * @param string $path
 * @return string
 */
function autodir(string $path): string {
  if (!file_exists($path)) {
    mkdir($path);
  }
  return $path;
}

###############################################################################
## Commands
$globalOptions = '[-N|--dry-run] [-S|--step]';

$c['app']->command("release $globalOptions new-version", function (string $newVersion, SymfonyStyle $io, Taskr $taskr) use ($c) {
  chdir($c['srcDir']);
  $taskr->subcommand('tag {{0|s}}', [$newVersion]);
  $taskr->subcommand('build');
  $taskr->subcommand('sign {{0|s}}', [$newVersion]);
  $taskr->subcommand('push {{0|s}}', [$newVersion]);
});

$c['app']->command("build $globalOptions", function (SymfonyStyle $io, Taskr $taskr) use ($c) {
  chdir($c['srcDir']);
  $io->title('Build PHAR');
  $taskr->passthru('bash build.sh');
});

$c['app']->command("clean $globalOptions", function (SymfonyStyle $io, Taskr $taskr) use ($c) {
  ['Init', $c['srcDir'], $c['buildDir']];
  chdir($c['srcDir']);
  $io->title('Cleanup');
  $taskr->passthru('rm -rf {{0|s}}', [$c['buildDir']]);
});

$c['app']->command("sign $globalOptions newVersion", function ($newVersion, SymfonyStyle $io, Taskr $taskr, \Crypt_GPG $gpg, $input) use ($c) {
  ['Init', $c['srcDir'], $c['distDir']];
  chdir($c['distDir']);
  $io->title('Generate checksum and GPG signature');

  $pharFile = "cv-$newVersion.phar";
  $sha256File = "cv-$newVersion.SHA256SUMS";

  $taskr->passthru('cp {{0|s}} {{1|s}}', [$c['boxOutputPhar'], $pharFile]);
  $taskr->passthru('sha256sum {{0|s}} > {{1|s}}', [$pharFile, $sha256File]);

  $io->writeln("Sign $pharFile ($pharFile.asc)");
  if (!$input->getOption('dry-run')) {
    $gpg->signFile($pharFile, "$pharFile.asc", \Crypt_GPG::SIGN_MODE_DETACHED);
    assertThat(!empty($gpg->verifyFile($pharFile, file_get_contents("$pharFile.asc"))), "$pharFile should have valid signature");
  }

  $io->writeln("Sign $sha256File ($sha256File.asc)");
  if (!$input->getOption('dry-run')) {
    $gpg->signFile($sha256File, "$sha256File.asc", \Crypt_GPG::SIGN_MODE_DETACHED);
    assertThat(!empty($gpg->verifyFile($sha256File, file_get_contents("$sha256File.asc"))), "$sha256File should have valid signature");
  }
});

$c['app']->command("tag $globalOptions new-version", function ($newVersion, SymfonyStyle $io, Taskr $taskr, Cmdr $cmdr, $git) use ($c) {
  ['Init', $c['srcDir'], $c['cvlibWorkDir'], $c['cvlibUpstream']];
  chdir($c['srcDir']);
  $io->title("Create tags ($newVersion)");

  $io->section('Tag cv.git');
  $git()->tag('-f', $newVersion);

  $io->section('Clone cv-lib.git');
  if (file_exists($c['cvlibWorkDir'])) {
    $taskr->passthru('rm -rf {{0|s}}', [$c['cvlibWorkDir']]);
  }
  $git()->clone('-b', 'master', $c['cvlibUpstream'], $c['cvlibWorkDir']);

  $io->section('Sync cv-lib.git');
  $flags = ['-a', '--delete'];
  if ($io->isVerbose()) {
    $flags[] = '-v';
  }
  $excludes = array_merge_recursive(...array_map(
    fn($ex) => ['--exclude', $ex],
    ['.git', '.gitrepo', '*~', 'vendor', 'composer.lock']
  ));
  $taskr->passthru("rsync {{0|@s}} {{1}}/./ {{2|s}}/./", [
    array_merge($flags, $excludes),
    $c['srcDir'] . '/lib',
    $c['cvlibWorkDir'],
  ]);

  $status = $c['input']->getOption('dry-run')
    ? 'changed...probably...' :
    $cmdr->run('cd {{0|s}} && git status --porcelain', [$c['cvlibWorkDir']]);

  if (!empty($status)) {
    $git($c['cvlibWorkDir'])->add('.')->commit('-m', "Update to $newVersion");
  }
  else {
    $io->note("No updates found for cv-lib.git");
  }

  $io->section('Tag cv-lib.git');
  $git($c['cvlibWorkDir'])->tag($newVersion);
});

$c['app']->command("push $globalOptions new-version", function ($newVersion, SymfonyStyle $io, Taskr $taskr, $git) use ($c) {
  ['Init', $c['srcDir'], $c['cvlibWorkDir'], $c['cvlibUpstream']];
  chdir($c['srcDir']);
  $io->title("Push $newVersion");

  $git($c['cvlibWorkDir'])
    ->push('origin', $newVersion)
    ->push('origin', 'master');

  $git()
    ->push('origin', $newVersion);
});

###############################################################################
## Go!

$c['app']->run();
