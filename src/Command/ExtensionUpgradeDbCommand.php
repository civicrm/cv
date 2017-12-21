<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\ExtensionUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ExtensionUpgradeDbCommand extends BaseExtensionCommand {

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
      ->setDescription('Apply DB upgrades for any extensions')
      ->setHelp('Apply DB upgrades for any extensions

Examples:
  cv ext:upgrade-db

Note:
  This subcommand does not output parseable data. For parseable output,
  consider using `cv api extension.upgrade`.
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    $output->writeln("<info>Applying database upgrades from extensions</info>");
    $result = $this->callApiSuccess($input, $output, 'Extension', 'upgrade', array());
    if (!empty($result['is_error'])) {
      return 1;
    }
  }

}
