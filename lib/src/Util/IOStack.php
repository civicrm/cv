<?php

namespace Civi\Cv\Util;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Track the active input/output objects.
 *
 * If you process sub-requests (with different input/output data), then you will
 * likely need to push+pop new copies of the input/output objects.
 */
class IOStack {

  private static $id = 0;

  protected $stack = [];

  /**
   * Add a new pair of input/output objects to the top of the stack.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Symfony\Component\Console\Application|null $app
   * @return scalar
   *   Internal identifier for the stack-frame. ID formatting is not guaranteed.
   */
  public function push(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output, ?\Symfony\Component\Console\Application $app = NULL) {
    ++static::$id;
    $app = $app ?: ($this->stack[0]['app'] ?? NULL);
    array_unshift($this->stack, [
      'id' => static::$id,
      'input' => $input,
      'output' => $output,
      'io' => new SymfonyStyle($input, $output),
      'app' => $app,
    ]);
    return static::$id;
  }

  public function pop(): array {
    return array_shift($this->stack);
  }

  /**
   * Get a current property of the current (top) stack-frame.
   *
   * @param string $property
   *   One of: 'input', 'output', 'io', 'id'
   * @return mixed
   */
  public function current(string $property) {
    return $this->stack[0][$property];
  }

  /**
   * Lookup a property from a particular stack-frame.
   *
   * @param scalar $id
   *   Internal identifier for the stack-frame.
   * @param string $property
   *   One of: 'input', 'output', 'io', 'id'
   * @return mixed|null
   */
  public function get($id, string $property) {
    foreach ($this->stack as $item) {
      if ($item['id'] === $id) {
        return $item[$property];
      }
    }
    return NULL;
  }

  public function replace($property, $value) {
    $this->stack[0][$property] = $value;
  }

  public function reset() {
    $this->stack = [];
  }

}
