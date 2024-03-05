<?php

namespace Civi\Cv;

class CvDispatcher {

  protected $listeners = [];

  public function dispatch($event, string $eventName = NULL) {
    if (!isset($this->listeners[$eventName])) {
      return $event;
    }

    ksort($this->listeners[$eventName], SORT_NUMERIC);
    foreach ($this->listeners[$eventName] as $listeners) {
      foreach ($listeners as $listener) {
        $listener($event);
      }
    }

    return $event;
  }

  public function addListener(string $eventName, $callback, int $priority = 0): void {
    $id = $this->getCallbackId($callback);
    $this->listeners[$eventName][$priority][$id] = $callback;
  }

  public function removeListener(string $eventName, $callback) {
    $id = $this->getCallbackId($callback);
    foreach ($this->listeners[$eventName] as &$listeners) {
      unset($listeners[$id]);
    }
  }

  /**
   * @param $callback
   * @return string
   */
  protected function getCallbackId($callback): string {
    if (is_string($callback)) {
      return $callback;
    }
    elseif (is_array($callback)) {
      return implode('::', $callback);
    }
    else {
      return spl_object_hash($callback);
    }
  }

}
