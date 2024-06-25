<?php

namespace Civi\Cv;

/**
 * Standard event class used for most Cv events.
 */
class CvEvent implements \ArrayAccess, \IteratorAggregate {

  private $propagationStopped = FALSE;

  /**
   * @var array
   */
  protected $arguments;

  /**
   * Encapsulate an event with $subject and $args.
   *
   * @param array $arguments Arguments to store in the event
   */
  public function __construct(array $arguments = []) {
    $this->arguments = $arguments;
  }

  /**
   * Get argument by key.
   *
   * @param string $key Key
   * @return mixed Contents of array key
   *
   * @throws \InvalidArgumentException if key is not found
   */
  public function getArgument($key) {
    if ($this->hasArgument($key)) {
      return $this->arguments[$key];
    }

    throw new \InvalidArgumentException(sprintf('Argument "%s" not found.', $key));
  }

  /**
   * Add argument to event.
   *
   * @param string $key Argument name
   * @param mixed $value Value
   *
   * @return $this
   */
  public function setArgument($key, $value) {
    $this->arguments[$key] = $value;

    return $this;
  }

  /**
   * Getter for all arguments.
   *
   * @return array
   */
  public function getArguments() {
    return $this->arguments;
  }

  /**
   * Set args property.
   *
   * @param array $args Arguments
   *
   * @return $this
   */
  public function setArguments(array $args = []) {
    $this->arguments = $args;

    return $this;
  }

  /**
   * Has argument.
   *
   * @param string $key Key of arguments array
   *
   * @return bool
   */
  public function hasArgument($key) {
    return \array_key_exists($key, $this->arguments);
  }

  /**
   * IteratorAggregate for iterating over the object like an array.
   *
   * @return \ArrayIterator
   */
  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->arguments);
  }

  /**
   * ArrayAccess for argument getter.
   *
   * @param string $key Array key
   * @return mixed
   * @throws \InvalidArgumentException if key does not exist in $this->args
   */
  #[\ReturnTypeWillChange]
  public function &offsetGet($offset) {
    return $this->arguments[$offset];
  }

  /**
   * ArrayAccess for argument setter.
   *
   * @param string $offset Array key to set
   * @param mixed $value Value
   */
  public function offsetSet($offset, $value): void {
    $this->setArgument($offset, $value);
  }

  /**
   * ArrayAccess for unset argument.
   *
   * @param string $offset Array key
   */
  public function offsetUnset($offset): void {
    if ($this->hasArgument($offset)) {
      unset($this->arguments[$offset]);
    }
  }

  /**
   * ArrayAccess has argument.
   *
   * @param string $offset Array key
   * @return bool
   */
  public function offsetExists($offset): bool {
    return $this->hasArgument($offset);
  }

  public function isPropagationStopped(): bool {
    return $this->propagationStopped;
  }

  public function setPropagationStopped(bool $propagationStopped): void {
    $this->propagationStopped = $propagationStopped;
  }

}
