<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\ExtensionTrait;
use Civi\Cv\Util\VerboseApi;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtensionEnableCommand extends CvCommand {

  use ExtensionTrait;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:enable')
      ->setAliases(['en', 'ext:install'])
      ->setDescription('Enable an extension')
      ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Refresh the local list of extensions (Default: Only refresh on cache-miss)')
      ->addOption('ignore-missing', NULL, InputOption::VALUE_NONE, 'If a requested extension is missing, skip it')
      ->addArgument('key-or-name', InputArgument::IS_ARRAY, 'One or more extensions to enable. Identify the extension by full key ("org.example.foobar") or short name ("foobar")')
      ->setHelp('Enable an extension

Examples:
  cv ext:enable org.example.foobar
  cv en foobar

Note:
  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.
  
  This subcommand does not output parseable data. For parseable output,
  consider using `cv api extension.install`.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    // Refresh extensions if (a) ---refresh enabled or (b) there's a cache-miss.
    $refresh = $input->getOption('refresh') ? 'yes' : 'auto';
    // $refresh = OptionalOption::parse(array('--refresh', '-r'), 'auto', 'yes');
    while (TRUE) {
      if ($refresh === 'yes') {
        $output->writeln("<info>Refreshing extension cache</info>");
        $result = VerboseApi::callApi3Success('Extension', 'refresh', array(
          'local' => TRUE,
          'remote' => FALSE,
        ));
        if (!empty($result['is_error'])) {
          return 1;
        }
      }

      [$foundKeys, $missingKeys] = $this->parseKeys($input, $output);
      if ($refresh == 'auto' && !empty($missingKeys)) {
        $output->writeln("<info>Extension cache does not contain requested item(s)</info>");
        $refresh = 'yes';
      }
      else {
        break;
      }
    }

    if ($missingKeys) {
      if ($input->getOption('ignore-missing')) {
        foreach ($missingKeys as $key) {
          $output->getErrorOutput()->writeln("<error>Warning: Skipped unrecognized extension \"$key\"</error>");
        }

      }
      else {
        foreach ($missingKeys as $key) {
          $output->getErrorOutput()->writeln("<error>Error: Unrecognized extension \"$key\"</error>");
        }
        return 1;
      }
    }

    foreach ($foundKeys as $key) {
      $output->writeln("<info>Enabling extension \"$key\"</info>");
    }

    $result = VerboseApi::callApi3Success('Extension', 'install', array(
      'keys' => $foundKeys,
    ));
    return empty($result['is_error']) ? 0 : 1;
  }

}
