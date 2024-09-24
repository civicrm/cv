<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\ExtensionTrait;
use Civi\Cv\Util\VerboseApi;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtensionUpgradeDbCommand extends CvCommand {

  use ExtensionTrait;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('ext:upgrade-db')
      ->setAliases(array())
      ->setDescription('Apply DB upgrades for any extensions (DEPRECATED)')
      ->setHelp('Apply DB upgrades for any extensions

Examples:
  cv ext:upgrade-db

Note:
  This subcommand does not output parseable data. For parseable output,
  consider using `cv api extension.upgrade`.

Deprecation:
  This command is now deprecated. Use "cv upgrade:db" to perform upgrades
  for core and/or extensions.
');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $output->writeln("<error>WARNING: \"ext:upgrade-db\" is deprecated. Use the main \"updb\" command instead.</error>");
    parent::initialize($input, $output);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $output->writeln("<info>Applying database upgrades from extensions</info>");
    $result = VerboseApi::callApi3Success('Extension', 'upgrade', array());
    if (!empty($result['is_error'])) {
      return 1;
    }
    return 0;
  }

}
