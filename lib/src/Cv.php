<?php

namespace Civi\Cv;

class Cv {

  protected static $instances = [];

  // /**
  //  * Get a PSR-16 cache service (`SimpleCache`).
  //  *
  //  * This is cache is file-based and user-scoped (e.g. `~/.cache/cv`).
  //  * Don't expect it to be high-performance...
  //  *
  //  * NOTE: At time of writing, this is not used internally - but can be used by a plugin.
  //  *
  //  * @param string $namespace
  //  * @return \Psr\SimpleCache\CacheInterface
  //  */
  // public static function cache($namespace = 'default') {
  //   if (!isset(self::$instances["cache.$namespace"])) {
  //     if (getenv('XDG_CACHE_HOME')) {
  //       $dir = getenv('XDG_CACHE_HOME');
  //     }
  //     elseif (getenv('HOME')) {
  //       $dir = getenv('HOME') . '/.cache';
  //     }
  //     else {
  //       throw new \RuntimeException("Failed to determine cache location");
  //     }
  //     $fsCache = new FilesystemAdapter($namespace, 600, $dir . DIRECTORY_SEPARATOR . 'cv');
  //     // In symfony/cache~3.x, the class name is weird.
  //     self::$instances["cache.$namespace"] = new Psr6Cache($fsCache);
  //   }
  //   return self::$instances["cache.$namespace"];
  // }

  /**
   * Get the system-wide event-dispatcher.
   *
   * @return \Civi\Cv\CvDispatcher
   */
  public static function dispatcher() {
    if (!isset(self::$instances['dispatcher'])) {
      self::$instances['dispatcher'] = new CvDispatcher();
    }
    return self::$instances['dispatcher'];
  }

  /**
   * Filter a set of data through an event.
   *
   * @param string $eventName
   * @param array $data
   *   Open-ended set of data.
   * @return array
   *   Filtered $data
   */
  public static function filter(string $eventName, array $data) {
    $event = new CvEvent($data);
    self::dispatcher()->dispatch($event, $eventName);
    return $event->getArguments();
  }

  /**
   * Get the plugin manager.
   *
   * @return \Civi\Cv\CvPlugins
   */
  public static function plugins(): CvPlugins {
    if (!isset(self::$instances['plugins'])) {
      self::$instances['plugins'] = new CvPlugins();
    }
    return self::$instances['plugins'];
  }

}
