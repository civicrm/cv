<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\ExtensionUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ExtensionDisableCommand extends BaseExtensionCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:disable')
      ->setAliases(array('dis'))
      ->setDescription('Disable an extension')
      ->addArgument('key-or-name', InputArgument::IS_ARRAY, 'One or more extensions to enable. Identify the extension by full key ("org.example.foobar") or short name ("foobar")')
      ->setHelp('Disable an extension

Examples:
  cv ext:disable org.example.foobar
  cv dis foobar

Note:
  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.

  This subcommand does not output parseable data. For parseable output,
  consider using `cv api extension.disable`.
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    list ($foundKeys, $missingKeys) = $this->parseKeys($input, $output);

    // Uninstall what's recognized or what looks like an ext key.
    $disableKeys = array_merge($foundKeys, preg_grep('/\./', $missingKeys));
    $missingKeys = preg_grep('/\./', $missingKeys, PREG_GREP_INVERT);

    foreach ($missingKeys as $key) {
      $output->writeln("<comment>Ignoring unrecognized extension \"$key\"</comment>");
    }
    foreach ($disableKeys as $key) {
      $output->writeln("<info>Disabling extension \"$key\"</info>");
    }

    $result = $this->callApiSuccess($input, $output, 'Extension', 'disable', array(
      'keys' => $disableKeys,
    ));
    return empty($result['is_error']) ? 0 : 1;
  }

}
