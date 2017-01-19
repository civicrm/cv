<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\ExtensionUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ExtensionPathCommand extends BaseExtensionCommand {

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:path')
      ->setAliases(array())
      ->setDescription('Look up an extension path')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getTabularFormats()) . ')', Encoder::getDefaultFormat('list'))
      ->addArgument('key-or-name', InputArgument::IS_ARRAY, 'One or more extensions to enable. Identify the extension by full key ("org.example.foobar") or short name ("foobar")')
      ->setHelp('Look up an extension path

Examples:
  cv ext:path
  cv ext:path cividiscount
  cv ext:path org.civicrm.modules.cividiscount

Note:
  If you don\'t request a specific extension, this command returns the path
  of the default extension container.

  Beginning circa CiviCRM v4.2+, it has been recommended that extensions
  include a unique long name ("org.example.foobar") and a unique short
  name ("foobar"). However, short names are not strongly guaranteed.
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    list ($foundKeys, $missingKeys) = $this->parseKeys($input, $output);

    foreach ($missingKeys as $key) {
      $output->getErrorOutput()->writeln("<comment>Ignoring unrecognized extension \"$key\"</comment>");
    }

    $mapper = \CRM_Extension_System::singleton()->getMapper();
    $results = array();
    foreach ($foundKeys as $key) {
      $results[] = array('key' => $key, 'path' => $mapper->keyToBasePath($key));
    }

    if (empty($missingKeys) && empty($foundKeys)) {
      $results[] = array('key' => $key, 'path' => \CRM_Core_Config::singleton()->extensionsDir);
    }

    $this->sendTable($input, $output, $results, array('path'));

    return empty($missingKeys) ? 0 : 1;
  }

}
