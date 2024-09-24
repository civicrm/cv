<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\Util\ExtensionTrait;
use Civi\Cv\Util\Process;
use Civi\Cv\Util\StructuredOutputTrait;
use Civi\Cv\Util\UrlCommandTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UrlCommand extends CvCommand {

  use ExtensionTrait;
  use StructuredOutputTrait;
  use UrlCommandTrait;

  protected function configure() {
    $this
      ->setName('url')
      ->setAliases(['open'])
      ->setDescription('Compose a URL to a CiviCRM page. (Optionally, open in a browser.)')
      ->configureOutputOptions(['tabular' => TRUE, 'availColumns' => 'type,expr,value', 'shortcuts' => ['table', 'list']])
      ->configureUrlOptions()
      ->addOption('open', 'O', InputOption::VALUE_NONE, 'Open a local web browser')
      // The original contract only displayed one URL. We subsequently added support for list/csv/table output which require multi-record orientation.
      // It's ambiguous whether JSON/serialize formats should stick to the old output or multi-record output.
      ->addOption('tabular', NULL, InputOption::VALUE_NONE, 'Force display in multi-record mode. (Enabled by default for list,csv,table formats.)')
      ->setHelp('
Compose a URL to a CiviCRM page or resource.

Examples: Lookup the site root
  cv url

Examples: Lookup URLs with the standard router
  cv url civicrm/dashboard
  cv url \'civicrm/a/#/mailing/123?angularDebug=1\'
  cv url civicrm/dashboard --user=bob --login

Examples: Open URLs in a local browser (Linux/OSX)
  cv open civicrm/dashboard
  cv open civicrm/dashboard --user=bob --login
  cv url civicrm/dashboard --open

Examples: Lookup URLs for extension resources
  cv url -x org.civicrm.module.cividiscount
  cv url -x cividiscount
  cv url -x cividiscount/css/example.css

Examples: Lookup URLs using configuration properties
  cv url -c imageUploadURL
  cv url -c imageUploadURL/example.png

Examples: Lookup URLs using dynamic expressions
  cv url -d \'[civicrm.root]/extern/ipn.php\'
  cv url -d \'[civicrm.files]\'
  cv url -d \'[cms.root]/index.php\'

Examples: Lookup multiple URLs
  cv url -x cividiscount -x volunteer civicrm/admin --out=table
  cv url -x cividiscount -x volunteer civicrm/admin --out=json --tabular


NOTE: To change the default output format, set CV_OUTPUT.

NOTE: If you use `--login` and do not have `authx`, then it prompts about
      enabling the extension. The extra I/O may influence some scripted
      use-cases.
');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    if ($input->getFirstArgument() === 'open') {
      $input->setOption('open', TRUE);
    }
    if (in_array($input->getOption('out'), Encoder::getTabularFormats())
    && !in_array($input->getOption('out'), Encoder::getFormats())) {
      $input->setOption('tabular', TRUE);
    }
    parent::initialize($input, $output);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $rows = $this->createUrls($input, $output);

    if ($input->getOption('open')) {
      $cmd = $this->pickCommand();
      if (!$cmd) {
        throw new \RuntimeException("Failed to locate 'xdg-open' or 'open'. Open not supported on this system.");
      }
      foreach ($rows as $row) {
        if (!empty($row['value'])) {
          $escaped = escapeshellarg($row['value']);
          Process::runOk(\Symfony\Component\Process\Process::fromShellCommandline("$cmd $escaped"));
        }
      }
    }

    if ($input->getOption('tabular')) {
      $columns = $this->parseColumns($input, array(
        'list' => array('value'),
      ));
      $this->sendTable($input, $output, $rows, $columns);
    }
    else {
      if (count($rows) !== 1) {
        $output->getErrorOutput()->writeln('<error>Detected multiple URLs. You must specify --tabular.</error>');
        return 1;
      }
      else {
        $this->sendResult($input, $output, $rows[0]['value']);
      }
    }

    return (in_array(NULL, $rows)) ? 1 : 0;
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
