<?php

namespace Civi\Cv;

use Civi\Cv\Util\IOStack;

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
   *   Ex: "cv.app.run"
   *   Note: This will dispatch listeners for both "cv.app.run" and "*.app.run".
   * @param array $data
   *   Open-ended set of data.
   *
   * @return array
   *   Filtered $data
   */
  public static function filter($eventName, array $data) {
    $event = new CvEvent($data);
    self::dispatcher()->dispatch($event, $eventName);
    return $event->getArguments();
  }

  /**
   * Get a list of input/output objects for pending commands.
   *
   * @return \Civi\Cv\Util\IOStack
   */
  public static function ioStack(): IOStack {
    if (!isset(static::$instances[__FUNCTION__])) {
      static::$instances[__FUNCTION__] = new IOStack();
    }
    return static::$instances[__FUNCTION__];
  }

  /**
   * @return \CvDeps\Symfony\Component\Console\Application|\Symfony\Component\Console\Application
   */
  public static function app() {
    return static::ioStack()->current('app');
  }

  /**
   * @return \CvDeps\Symfony\Component\Console\Input\InputInterface|\Symfony\Component\Console\Input\InputInterface
   */
  public static function input() {
    return static::ioStack()->current('input');
  }

  /**
   * Get a reference to STDOUT (with support for highlighting) for current action.
   * )
   * @return \CvDeps\Symfony\Component\Console\Output\OutputInterface|\Symfony\Component\Console\Output\OutputInterface
   */
  public static function output() {
    return static::ioStack()->current('output');
  }

  /**
   * Get a reference to STDERR (with support for highlighting) for current action .
   *
   * @return \CvDeps\Symfony\Component\Console\Output\OutputInterface|\Symfony\Component\Console\Output\OutputInterface
   */
  public static function errorOutput() {
    $out = static::output();
    return method_exists($out, 'getErrorOutput') ? $out->getErrorOutput() : $out;
  }

  /**
   * @return \CvDeps\Symfony\Component\Console\Style\StyleInterface|\Symfony\Component\Console\Style\StyleInterface
   */
  public static function io() {
    return static::ioStack()->current('io');
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
