<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Datasource;
use Civi\Cv\Util\Process;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SqlCliCommand extends CvCommand {

  protected function configure() {
    $this
      ->setName('sql')
      ->setAliases(array('sql:cli'))
      ->setDescription('Open the SQL CLI')
      ->addOption('eval', 'e', InputOption::VALUE_NONE, 'Enable evaluation of preprocessing expressions. (Pipe-only)')
      ->addOption('target', 'T', InputOption::VALUE_REQUIRED, 'Target DB (civi, cms)', 'civi')
      ->addOption('dry-run', 'N', InputOption::VALUE_NONE, 'Preview the SQL commands. Do not execute. (Pipe-only)')
      ->setHelp("
The \"sql\" command allows you to execute SQL interactively or through a pipe.

This is a wrapper for the \"mysql\" CLI command -- the general semantics
and notation are therefore inherited from MySQL's CLI.

Optionally, when piping in SQL, the \"--eval\" option adds support for extra
pre-processing features. Specifically, it interpolates and escapes environment variables:

  export USERNAME=badguy
  echo 'DELETE FROM users WHERE username = @ENV[USERNAME]' | cv sql -e

The ENV expressions are prefixed to indicate their escaping rule:

  @ENV[FOO]    Produces an escaped version of string FOO
  #ENV[FOO]    Produces the numerical value of FOO (or fails)
  !ENV[FOO]    Produces the raw, unescaped string version of FOO
");
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    if ($input->getOption('dry-run') && $output->getVerbosity() <= OutputInterface::VERBOSITY_NORMAL) {
      $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
    }
    parent::initialize($input, $output);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $datasource = new Datasource();
    $datasource->loadFromCiviDSN($this->pickDsn($input->getOption('target')));

    $mysql = Process::findCommand('mysql');
    if (Process::isShellScript($mysql) && !static::supportsDefaultsFile($mysql)) {
      $output->getErrorOutput()->writeln("<info>[SqlCommand]</info> <comment>WARNING: The mysql command appears to be a wrapper script. In some environments, this may interfere with credential passing.</comment>");
    }

    $cmd = escapeshellcmd($mysql) . " " . $datasource->toMySQLArguments($this->findCreateTmpDir());
    $output->getErrorOutput()->writeln("<info>[SqlCommand]</info> Launch sub-command: <comment>$cmd</comment>", OutputInterface::VERBOSITY_VERBOSE);

    if (!$input->getOption('dry-run')) {
      $process = proc_open(
        $cmd,
        array(
          0 => $input->getOption('eval') ? array('pipe', 'r') : STDIN,
          1 => STDOUT,
          2 => STDERR,
        ),
        $pipes
      );
    }

    $finalSql = NULL;
    if ($input->getOption('dry-run') || $input->getOption('eval')) {
      $rawSql = trim(file_get_contents('php://stdin'), "\r\n");
      $output->getErrorOutput()->writeln("<info>[SqlCommand]</info> Raw SQL:   <comment>$rawSql</comment>", OutputInterface::VERBOSITY_VERBOSE);

      $finalSql = $input->getOption('eval') ? $this->filterSql($rawSql) : $rawSql;
      $output->getErrorOutput()->writeln("<info>[SqlCommand]</info> Final SQL: <comment>$finalSql</comment>", OutputInterface::VERBOSITY_VERBOSE);
    }

    if (!$input->getOption('dry-run')) {
      if ($finalSql !== NULL) {
        fwrite($pipes[0], $finalSql);
        fclose($pipes[0]);
      }
      return proc_close($process);
    }
    else {
      return 0;
    }
  }

  protected function filterSql($sql) {
    $changed = preg_replace_callback('/([#!@])ENV\[([a-zA-Z0-9_]+)\]/', function ($matches)  use ($pdo) {
      $value = getenv($matches[2]);
      switch ($matches[1]) {
        // raw variable
        case '!':
          return $value;

        // numeric variable
        case '#':
          if (!is_numeric($value)) {
            throw new \RuntimeException("Environment variable " . $matches[2] . " is not numeric!");
          }
          return $value;

        // string variable
        case '@':
          return '"' . \CRM_Core_DAO::escapeString($value) . '"';

        default:
          throw new \RuntimeException("Variable prefix not recognized.");
      }
    }, $sql);
    return $changed;
  }

  /**
   * @return string
   */
  protected function findCreateTmpDir() {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cv-cli-' . posix_getuid();
    if (!is_dir($path)) {
      mkdir($path, 0700);
    }
    return $path;
  }

  /**
   * @param string $target
   *   Ex: 'cms', 'civi'
   * @return string
   *   Civi-style DSN
   *   Ex: 'mysql://user:pass@host/db'
   */
  protected function pickDsn($target) {
    $dsn = NULL;

    switch ($target) {
      case 'civi':
        $dsn = CIVICRM_DSN;
        break;

      case 'cms':
        $dsn = \CRM_Core_Config::singleton()->userFrameworkDSN;
        break;
    }

    if (empty($dsn)) {
      throw new \RuntimeException("Failed determine DSN for target \"$target\".");
    }

    return $dsn;
  }

  protected function supportsDefaultsFile(string $bin): bool {
    $code = file_get_contents($bin);
    return preg_match(';@ respect --defaults-file;', $code);
  }

}
