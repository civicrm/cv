<?php
namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class OptionCallbackTrait
 * @package Civi\Cv\Util
 *
 * This trait allows you to define an initialization callback for each option.
 *
 * Ex:
 *   $this->addOption('foo', ...)
 *       ->addOptionCallback('foo', function(InputInterface $input, OutputInterface $output, InputOption $option) { ... })
 */
trait OptionCallbackTrait {

  /**
   * @return \Symfony\Component\Console\Input\InputDefinition
   *   An InputDefinition instance
   * @see \Symfony\Component\Console\Command\Command::getDefinition()
   */
  abstract public function getDefinition();

  /**
   * @var array
   *   Array([string $optionName, callable $callback]).
   */
  private $optionCallbacks = [];

  /**
   * @param string $name
   *   The name of the option to
   * @param callable $callback
   *   Function(InputInterface $input, OutputInterface, $output, string $optionName)
   * @return $this
   */
  public function addOptionCallback($name, $callback) {
    $this->optionCallbacks[] = [$name, $callback];
    return $this;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return $this
   */
  protected function runOptionCallbacks(InputInterface $input, OutputInterface $output) {
    $defn = $this->getDefinition();

    foreach ($this->optionCallbacks as $optionCallbackDefn) {
      list ($optionName, $callback) = $optionCallbackDefn;
      if ($defn->hasOption($optionName)) {
        $callback($input, $output, $defn->getOption($optionName));
      }
    }

    return $this;
  }

}
