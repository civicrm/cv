<?php

namespace Civi\Cv;

class CvDispatcher {

  protected $listeners = [];

  /**
   * @param object $event
   * @param string|null $eventName
   * @return object
   */
  public function dispatch($event, $eventName = NULL) {
    $activeListeners = [];

    if ($eventName === NULL) {
      $eventName = get_class($event);
    }
    $eventNames = [$eventName, preg_replace(';^\w+\.;', '*.', $eventName)];
    foreach ($eventNames as $name) {
      if (isset($this->listeners[$name])) {
        $activeListeners = array_merge($activeListeners, $this->listeners[$name]);
      }
    }

    usort($activeListeners, function ($a, $b) {
      if ($a['priority'] !== $b['priority']) {
        return $a['priority'] - $b['priority'];
      }
      else {
        return $a['natPriority'] - $b['natPriority'];
      }
    });
    foreach ($activeListeners as $listener) {
      call_user_func($listener['callback'], $event);
    }

    return $event;
  }

  public function addListener(string $eventName, $callback, int $priority = 0): void {
    static $natPriority = 0;
    $natPriority++;
    $id = $this->getCallbackId($callback);
    $this->listeners[$eventName][$id] = ['callback' => $callback, 'priority' => $priority, 'natPriority' => $natPriority];
  }

  public function removeListener(string $eventName, $callback) {
    $id = $this->getCallbackId($callback);
    unset($this->listeners[$eventName][$id]);
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
