<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Util\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class UrlCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('url')
      ->setDescription('Compose a URL to a CiviCRM page')
      ->addArgument('path')
      ->addOption('out', NULL, InputArgument::OPTIONAL, 'Specify return format (json,none,php,pretty,shell)', \Civi\Cv\Encoder::getDefaultFormat())
      ->addOption('relative', 'r', InputOption::VALUE_NONE, 'Prefer relative URL format. (Default: absolute)')
      ->addOption('frontend', 'f', InputOption::VALUE_NONE, 'Generate a frontend URL (Default: backend)')
      ->addOption('open', 'O', InputOption::VALUE_NONE, 'Open a local web browser')
      ->setHelp('
Compose a URL to a CiviCRM page

Examples:
  cv url civicrm/dashboard
  cv url civicrm/dashboard --open
  cv url \'civicrm/a/#/mailing/123?angularDebug=1\'

NOTE: To change the default output format, set CV_OUTPUT.
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    $path = parse_url($input->getArgument('path'), PHP_URL_PATH);
    $query = parse_url($input->getArgument('path'), PHP_URL_QUERY);
    $fragment = parse_url($input->getArgument('path'), PHP_URL_FRAGMENT);

    $value = \CRM_Utils_System::url(
      $path,
      $query,
      !$input->getOption('relative'),
      $fragment,
      FALSE,
      (bool) $input->getOption('frontend'),
      (bool) !$input->getOption('frontend')
    );

    if ($input->getOption('open')) {
      $cmd = $this->pickCommand();
      if (!$cmd) {
        throw new \RuntimeException("Failed to locate 'xdg-open' or 'open'. Open not supported on this system.");
      }
      $escaped = escapeshellarg($value);
      Process::runOk(new \Symfony\Component\Process\Process("$cmd $escaped"));
    }

    $this->sendResult($input, $output, $value);
  }

  protected function pickCommand($commands = array('xdg-open', 'open')) {
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    foreach ($commands as $cmd) {
      foreach ($paths as $path) {
        $file = $path . DIRECTORY_SEPARATOR . $cmd;
        if (is_file($file)) {
          return $cmd;
        }
      }
    }
    return NULL;
  }

}
