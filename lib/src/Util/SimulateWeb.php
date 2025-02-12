<?php

namespace Civi\Cv\Util;

class SimulateWeb {

  /**
   * @param string $effectiveUrl
   * @param string $scriptFile
   * @param string $serverSoftware
   */
  public static function apply($effectiveUrl, $scriptFile, $serverSoftware) {
    $_SERVER['SCRIPT_FILENAME'] = $scriptFile;
    $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
    $_SERVER['SERVER_SOFTWARE'] = $serverSoftware;
    $_SERVER['REQUEST_METHOD'] = 'GET';

    if ($effectiveUrl) {
      foreach (SimulateWeb::convertUrlToCgiVars($effectiveUrl) as $key => $value) {
        $_SERVER[$key] = $value;
      }
    }

    if (ord($_SERVER['SCRIPT_NAME']) != 47) {
      $_SERVER['SCRIPT_NAME'] = '/' . $_SERVER['SCRIPT_NAME'];
    }
  }

  public static function convertUrlToCgiVars(?string $url): array {
    if (strpos($url, '://') === FALSE) {
      throw new \LogicException("convertUrlToCgiVars() expects a URL");
    }

    $parts = parse_url($url);
    $result = [];
    $result['SERVER_NAME'] = $parts['host'];
    if (!empty($parts['port'])) {
      $result['HTTP_HOST'] = $parts['host'] . ':' . $parts['port'];
      $result['SERVER_PORT'] = $parts['port'];
    }
    else {
      $result['HTTP_HOST'] = $parts['host'];
      $result['SERVER_PORT'] = $parts['scheme'] === 'http' ? 80 : 443;
    }
    if ($parts['scheme'] === 'https') {
      $result['HTTPS'] = 'on';
    }
    return $result;
  }

  public static function detectEnvUrl(): ?string {
    if ($host = static::detectEnvHost()) {
      return static::detectEnvScheme() . '://' . $host;
    }
    return NULL;
  }

  /**
   * If the user has environment-variables like HTTP_HOST, take that as a sign of
   * the intended host.
   *
   * @return string|null
   */
  public static function detectEnvHost(): ?string {
    if (array_key_exists('HTTP_HOST', $_SERVER) && strpos($_SERVER['HTTP_HOST'], '//') === FALSE) {
      $url = $_SERVER['HTTP_HOST'];
      if (array_key_exists('HTTP_PORT', $_SERVER)) {
        $url .= $_SERVER['HTTP_PORT'];
      }
      return $url;
    }
    return NULL;
  }

  public static function detectEnvScheme(): ?string {
    return (($_SERVER['SERVER_PORT'] ?? NULL) === 443 || ($_SERVER['HTTPS'] ?? NULL) === 'on') ? 'https' : 'http';
  }

  public static function prependDefaultScheme(?string $url): string {
    if ($url === NULL || $url === '') {
      return $url;
    }
    elseif (strpos($url, '://') !== FALSE) {
      return $url;
    }
    else {
      return static::detectEnvScheme() . '://' . $url;
    }
  }

  public static function localhost(): string {
    return static::prependDefaultScheme('localhost');
  }

}
