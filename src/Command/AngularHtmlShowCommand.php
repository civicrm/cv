<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AngularHtmlShowCommand extends CvCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ang:html:show')
      ->setAliases(array())
      ->setDescription('Show an Angular HTML file')
      ->addOption('raw', NULL, InputOption::VALUE_NONE,
        'Display the raw (unmodified/unhooked) content')
      ->addOption('diff', NULL, InputOption::VALUE_NONE,
        'Display the diff (from pristine code to live code)')
      ->addArgument('file', InputArgument::REQUIRED | InputArgument::IS_ARRAY,
        'The file to display. (The leading "~" is optional.)')
      ->setHelp('Show an Angular HTML file

Examples:
  cv ang:html:show crmMailing/BlockMailing.html
  cv ang:html:show crmMailing/BlockMailing.html --diff
  cv ang:html:show crmMailing/BlockMailing.html --diff | colordiff
  cv ang:html:show "~/crmMailing/BlockMailing.html"
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!$input->getOption('user')) {
      $output->getErrorOutput()->writeln("<comment>For a full list, try passing --user=[username].</comment>");
    }

    $ang = \Civi::service('angular');
    $result = 0;

    if (!is_callable(array($ang, 'getRawPartials'))) {
      $output->writeln('<error>This version of CiviCRM does not support getRawPartials().</error>');
      return 4;
    }

    foreach ($input->getArgument('file') as $file) {
      $file = '~/' . preg_replace(';^~/;', '', $file);
      if (!preg_match(';^~/([^\/]+)/.+;', $file, $matches)) {
        $output->writeln("<error>File name is malformed ($file)</error>");
        $result = 1;
        continue;
      }
      $module = $matches[1];

      if ($input->getOption('diff')) {
        $command = Process::findCommand('diff');
        if (!$command) {
          $output->writeln("<error>Command 'diff' is not available.</error>");
          $result = 3;
          continue;
        }
        $livePartials = $ang->getPartials($module);
        $rawPartials = $ang->getRawPartials($module);
        $prefix = preg_replace(';[^a-zA-Z0-9_\-\.];', '', $file) . '-';
        $liveFile = tempnam(sys_get_temp_dir(), $prefix);
        $rawFile = tempnam(sys_get_temp_dir(), $prefix);
        $coder = new \Civi\Angular\Coder();
        file_put_contents($rawFile, isset($rawPartials[$file]) ? $coder->recode($rawPartials[$file]) : '');
        file_put_contents($liveFile, isset($livePartials[$file]) ? $coder->recode($livePartials[$file]) : '');
        $cmd = sprintf("diff -u %s %s", escapeshellarg($rawFile), escapeshellarg($liveFile));
        $content = `$cmd`;
        if (empty($content)) {
          $content = 'No differences found';
        }
        unlink($rawFile);
        unlink($liveFile);
      }
      elseif ($input->getOption('raw')) {
        $rawPartials = $ang->getRawPartials($module);
        $content = isset($rawPartials[$file]) ? $rawPartials[$file] : NULL;
      }
      else {
        $livePartials = $ang->getPartials($module);
        $content = isset($livePartials[$file]) ? $livePartials[$file] : NULL;
      }

      if ($content === NULL) {
        $output->writeln("<error>File \"$file\" not found in module \"$module\".</error>");
        $result = 2;
      }
      else {
        $output->writeln($content);
      }
    }

    return $result;
  }

}
