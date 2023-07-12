<?php
namespace Civi\Cv;

use Civi\Cv\Util\Filesystem;

class Config {

  public static function read() {
    $file = self::getFileName();
    if ($file && file_exists($file)) {
      $buf = file_get_contents($file);
      $config = json_decode($buf, TRUE);
      if (!empty($buf) && $config === NULL) {
        throw new \RuntimeException("Config file ($file) contains malformed JSON.");
      }
      return $config;
    }
    else {
      return array();
    }
  }

  /**
   * Update a section of the config
   *
   * @param callable $filter
   *   A filter function which accepts the old config and returns the new config.
   *   Ex: function($data) { $data['x']=123; return $data; }
   * @return bool
   * @throws \RuntimeException
   */
  public static function update($filter) {
    $fs = new Filesystem();
    return $fs->update(self::getFileName(), function ($rawIn) use ($filter) {
      $data = empty($rawIn) ? array() : json_decode($rawIn, TRUE);
      $data = call_user_func($filter, $data);
      return Encoder::encode($data, 'json-pretty');
    });
  }

  /**
   * @return string
   */
  public static function getFileName() {
    if (getenv('CV_CONFIG')) {
      return getenv('CV_CONFIG');
    }
    elseif (getenv('HOME')) {
      return getenv('HOME') . '/.cv.json';
    }
    else {
      throw new \RuntimeException("Failed to determine file path for 'cv.json'.");
    }
  }

}
