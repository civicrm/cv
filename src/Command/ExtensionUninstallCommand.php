<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\ExtensionTrait;
use Civi\Cv\Util\VerboseApi;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtensionUninstallCommand extends CvCommand {

  use ExtensionTrait;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:uninstall')
      ->setAliases(array())
      ->setDescription('Uninstall an extension and purge its data')
      ->addArgument('key-or-name', InputArgument::IS_ARRAY, 'One or more extensions to enable. Identify the extension by full key ("org.example.foobar") or short name ("foobar")')
      ->setHelp('Uninstall an extension and purge its data.

Examples:
  cv ext:uninstall org.example.foobar
  cv ext:uninstall foobar

Note:
  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.

  This subcommand does not output parseable data. For parseable output,
  consider using `cv api extension.uninstall`.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    [$foundKeys, $missingKeys] = $this->parseKeys($input, $output);

    // Uninstall what's recognized or what looks like an ext key.
    $uninstallKeys = array_merge($foundKeys, preg_grep('/\./', $missingKeys));
    $missingKeys = preg_grep('/\./', $missingKeys, PREG_GREP_INVERT);

    foreach ($missingKeys as $key) {
      $output->writeln("<comment>Ignoring unrecognized extension \"$key\"</comment>");
    }
    foreach ($uninstallKeys as $key) {
      $output->writeln("<info>Uninstalling extension \"$key\"</info>");
    }

    $result = VerboseApi::callApi3Success('Extension', 'disable', array(
      'keys' => $uninstallKeys,
    ));
    if (!empty($result['is_error'])) {
      return 1;
    }

    $result = VerboseApi::callApi3Success('Extension', 'uninstall', array(
      'keys' => $uninstallKeys,
    ));
    return empty($result['is_error']) ? 0 : 1;
  }

}
