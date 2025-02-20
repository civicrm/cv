<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\BootTrait;
use Civi\Cv\Util\OptionCallbackTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `CvCommand` is a Symfony `Command` with support for bootstrapping CiviCRM/CMS.
 *
 * - From end-user POV, the command accepts options like --user, --level, --url.
 * - From dev POV, the command allows you to implement `execute()` method without needing to
 *   explicitly boot Civi.
 * - From dev POV, you may fine-tune command by changing the $bootOptions / getBootOptions().
 */
class CvCommand extends Command {

  use OptionCallbackTrait;
  use BootTrait;

  public function mergeApplicationDefinition($mergeArgs = TRUE) {
    parent::mergeApplicationDefinition($mergeArgs);
    $this->mergeBootDefinition($this->getDefinition());
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->autoboot($input, $output);
    parent::initialize($input, $output);
    $this->runOptionCallbacks($input, $output);
  }

}
