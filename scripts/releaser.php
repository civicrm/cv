#!/usr/bin/env pogo
<?php
#!depdir ../extern/releaser-deps
#!require clippy/std: ~0.4.4
#!require clippy/container: '~1.2'

###############################################################################
## Bootstrap
namespace Clippy;

use Symfony\Component\Console\Style\SymfonyStyle;

assertThat(PHP_SAPI === 'cli', "Releaser may only run via CLI");
$c = clippy()->register(plugins());

###############################################################################
## Configuration

$c['ghRepo'] = 'totten/cv';
// FIXME // $c['ghRepo'] = 'civicrm/cv';
$c['srcDir'] = fn() => realpath(dirname(pogo_script_dir()));
$c['buildDir'] = fn($srcDir) => autodir("$srcDir/build");
$c['distDir'] = fn($buildDir) => autodir("$buildDir/dist");
$c['toolName'] = fn($boxOutputPhar) => preg_replace(';\.phar$;', '', basename($boxOutputPhar));

// Ex: "v1.2.3" ==> publishedTagName="v1.2.3", publishedPharName="mytool-1.2.3.phar"
// Ex: "1.2.3"  ==> publishedTagName="v1.2.3", publishedPharName="mytool-1.2.3.phar"
$c['publishedTagName'] = fn($input) => preg_replace(';^v?([\d\.]+);', 'v\1', $input->getArgument('new-version'));
$c['publishedPharName'] = fn($toolName, $publishedTagName) => $toolName . "-" . preg_replace(';^v;', '', $publishedTagName) . '.phar';

$c['cvlibUpstream'] = fn() => 'file:///tmp/cv-lib-upstream';
// FIXME // $c['cvlibUpstream'] = fn() => 'git@github.com:civicrm/cv-lib.git';
$c['cvlibWorkDir'] = fn($buildDir) => $buildDir . '/cv-lib';

###############################################################################
## Services and other helpers

$c['gpg'] = function(Credentials $cred): \Crypt_GPG {
  // It's easier to sign multiple files if we use Crypt_GPG wrapper API.
  #!require pear/crypt_gpg: ~1.6.4
  $gpg = new \Crypt_GPG(['binary' => trim(`which gpg`)]);
  $gpg->addSignKey($cred->get('GPG_KEY'), $cred->get('GPG_PASSPHRASE'));
  return $gpg;
};

$c['boxJson'] = function(string $srcDir): array {
  $file = $srcDir . '/box.json';
  assertThat(file_exists($file), "File not found: $file");
  return fromJSON(file_get_contents($file));
};

// Ex: /home/me/src/mytool/bin/mytool.phar
$c['boxOutputPhar'] = function($srcDir, $boxJson) {
  assertThat(!empty($boxJson['output']));
  return $srcDir . '/' . $boxJson['output'];
};

/**
 * Map 'git' subcommands to object-methods. Execute via Taskr. Compare:
 *
 * Bash:
 *   git push -f origin $version
 * PHP:
 *   $git()->push('-f', 'origin', $version);
 * PHP (advanced):
 *   $git('/path/to/repo')->push('-f', 'origin', $version);
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
$globalOptions = '[-N|--dry-run] [-S|--step] new-version';

$c['app']->command("release $globalOptions", function (string $publishedTagName, SymfonyStyle $io, Taskr $taskr) use ($c) {
  $taskr->subcommand('tag {{0|s}}', [$publishedTagName]);
  $taskr->subcommand('build {{0|s}}', [$publishedTagName]);
  $taskr->subcommand('sign {{0|s}}', [$publishedTagName]);
  $taskr->subcommand('upload {{0|s}}', [$publishedTagName]);
  // TODO: $taskr->subcommand('clean {{0|s}}', [$publishedTagName]);
});

$c['app']->command("tag $globalOptions", function ($publishedTagName, SymfonyStyle $io, Taskr $taskr, Cmdr $cmdr, $git) use ($c) {
  $io->title("Create tags ($publishedTagName)");
  ['Init', $c['srcDir'], $c['cvlibWorkDir'], $c['cvlibUpstream']];
  chdir($c['srcDir']);

  $io->section("Tag cv.git ($publishedTagName)");
  $git()->tag('-f', $publishedTagName);

  $io->section('Clone cv-lib.git');
  $taskr->passthru('if [ -e {{0|s}} ]; then rm -rf {{0|s}}; fi', [$c['cvlibWorkDir']]);
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
    $git($c['cvlibWorkDir'])->add('.')->commit('-m', "Update to $publishedTagName");
  }
  else {
    $io->note("No updates found for cv-lib.git");
  }

  $io->section("Tag cv-lib.git ($publishedTagName)");
  $git($c['cvlibWorkDir'])->tag($publishedTagName);
});

$c['app']->command("build $globalOptions", function (SymfonyStyle $io, Taskr $taskr) use ($c) {
  $io->title('Build PHAR');
  chdir($c['srcDir']);
  $taskr->passthru('bash build.sh');
});

$c['app']->command("sign $globalOptions", function (SymfonyStyle $io, Taskr $taskr, \Crypt_GPG $gpg, $input) use ($c) {
  $io->title('Generate checksum and GPG signature');
  ['Init', $c['srcDir'], $c['distDir'], $c['publishedPharName']];
  chdir($c['distDir']);

  $pharFile = $c['publishedPharName'];
  $sha256File = preg_replace(';\.phar$;', '.SHA256SUMS', $pharFile);

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

$c['app']->command("upload $globalOptions", function ($publishedTagName, SymfonyStyle $io, Taskr $taskr, $git, Credentials $cred) use ($c) {
  $io->title("Upload code and build artifacts");
  ['Init', $c['srcDir'], $c['cvlibWorkDir'], $c['cvlibUpstream'], $c['ghRepo'], $c['distDir'], $c['publishedPharName']];
  chdir($c['srcDir']);

  $vars = [
    'GH' => $cred->get('GH_TOKEN', $c['ghRepo']),
    'VER' => $publishedTagName,
    'REPO' => $c['ghRepo'],
    'PHAR' => $c['distDir'] . '/' . $c['publishedPharName'],
  ];

  $git($c['cvlibWorkDir'])->push('origin', $publishedTagName, 'master');
  $git()->push('origin', $publishedTagName);

  $taskr->passthru('GH_TOKEN={{GH|s}} gh release create {{VER|s}} --repo {{REPO|s}} --generate-notes', $vars);
  $taskr->passthru('GH_TOKEN={{GH|s}} gh release upload {{VER|s}} --repo {{REPO|s}} --clobber {{PHAR|s}} {{PHAR|s}}.asc', $vars);
});

$c['app']->command("clean $globalOptions", function (SymfonyStyle $io, Taskr $taskr) use ($c) {
  ['Init', $c['srcDir'], $c['buildDir'], $c['boxOutputPhar']];

  $io->title('Clean build directory');
  chdir($c['srcDir']);

  $taskr->passthru('rm -rf {{0|@s}}', [[$c['buildDir'], $c['boxOutputPhar']]]);
});

###############################################################################
## Go!

$c['app']->run();
