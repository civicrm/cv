<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\BootTrait;
use Civi\Cv\Util\OptionCallbackTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseCommand extends Command {

  use OptionCallbackTrait;
  use BootTrait;

  public function getBootOptions(): array {
    return [
      'auto' => TRUE,
      'default' => 'full|cms-full',
      'allow' => ['full|cms-full', 'full', 'cms-full', 'settings', 'classloader', 'cms-only', 'none'],
    ];
  }

  public function mergeApplicationDefinition($mergeArgs = TRUE) {
    parent::mergeApplicationDefinition($mergeArgs);
    $bootOptions = $this->getBootOptions();
    $this->getDefinition()->getOption('level')->setDefault($bootOptions['default']);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $bootOptions = $this->getBootOptions();
    if (!in_array($input->getOption('level'), $bootOptions['allow'])) {
      throw new \LogicException(sprintf("Command called with with level (%s) but only accepts levels (%s)",
        $input->getOption('level'), implode(', ', $bootOptions['allow'])));
    }

    if (!$this->isBooted() && ($bootOptions['auto'] ?? TRUE)) {
      $this->boot($input, $output);
    }

    parent::initialize($input, $output);
    $this->runOptionCallbacks($input, $output);
  }

}
