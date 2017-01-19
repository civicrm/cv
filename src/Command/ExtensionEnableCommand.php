<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\ExtensionUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ExtensionEnableCommand extends BaseExtensionCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:enable')
      ->setAliases(array('en'))
      ->setDescription('Enable an extension')
      ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Refresh the local list of extensions')
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
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    if ($input->getOption('refresh')) {
      $output->writeln("<info>Refreshing extensions</info>");
      $result = $this->callApiSuccess($input, $output, 'Extension', 'refresh', array(
        'local' => TRUE,
        'remote' => FALSE,
      ));
      if (!empty($result['is_error'])) {
        return 1;
      }

    }

    list ($foundKeys, $missingKeys) = $this->parseKeys($input, $output);

    if ($missingKeys) {
      foreach ($missingKeys as $key) {
        $output->writeln("<error>Error: Unrecognized extension \"$key\"</error>");
      }
      return 1;
    }

    foreach ($foundKeys as $key) {
      $output->writeln("<info>Enabling extension \"$key\"</info>");
    }

    $result = $this->callApiSuccess($input, $output, 'Extension', 'install', array(
      'keys' => $foundKeys,
    ));
    return empty($result['is_error']) ? 0 : 1;
  }

}
