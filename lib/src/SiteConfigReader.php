<?php
namespace Civi\Cv;

class SiteConfigReader {

  protected $settingsFile;

  protected $cache = array();

  /**
   * SiteConfigReader constructor.
   * @param string $settingsFile
   *   The identifier for the site we want to read.
   */
  public function __construct($settingsFile) {
    $this->settingsFile = $settingsFile;
  }

  /**
   * @param array $parts
   *   Any of 'buildkit', 'home', 'active'.
   * @return array
   */
  public function compile($parts) {
    $data = array();

    foreach ($parts as $part) {
      switch ($part) {
        case 'buildkit':
          $data = array_merge($data, $this->readBuildkitConfig());
          break;

        case 'home':
          $data = array_merge($data, $this->readHomeConfig());
          break;

        case 'active':
          $data = array_merge($data, $this->readActiveConfig());
          break;
      }

    }
    return $this->clean($data);
  }

  /**
   * Read the config vars from buildkit's .sh file.
   *
   * @return array
   */
  public function readBuildkitConfig() {
    if (!isset($this->cache['buildkit'])) {
      $shFile = BuildkitReader::findShFile($this->settingsFile);
      $this->cache['buildkit'] = $shFile ? BuildkitReader::readShFile($shFile) : array();
    }
    return $this->cache['buildkit'];
  }

  /**
   * Read the config vars from ~/.cv.json.
   *
   * @return array
   */
  public function readHomeConfig() {
    if (!isset($this->cache['home'])) {
      $config = Config::read();
      $this->cache['home'] = isset($config['sites'][$this->settingsFile])
        ? $config['sites'][$this->settingsFile] : array();
      $this->cache['home']['CONFIG_FILE'] = Config::getFileName();
    }
    return $this->cache['home'];
  }

  /**
   * @return array
   */
  public function readActiveConfig() {
    if (!defined('CIVICRM_SETTINGS_PATH') || CIVICRM_SETTINGS_PATH !== $this->settingsFile) {
      return array();
    }

    $paths = is_callable(array('Civi', 'paths')) ? \Civi::paths() : NULL;
    $log = \CRM_Core_Error::createDebugLogger();
    $data = array(
      'CMS_DB_DSN' => CIVICRM_UF_DSN,
      'CMS_VERSION' => \CRM_Core_Config::singleton()->userSystem->getVersion(),
      'CIVI_DB_DSN' => CIVICRM_DSN,
      'CIVI_SITE_KEY' => CIVICRM_SITE_KEY,
      'CIVI_VERSION' => \CRM_Utils_System::version(),
      'CIVI_SETTINGS' => CIVICRM_SETTINGS_PATH,
      'CIVI_TEMPLATEC' => \CRM_Core_Config::singleton()->templateCompileDir,
      'CIVI_LOG' => is_callable(['CRM_Core_Error', 'generateLogFileName']) ? \CRM_Core_Error::generateLogFileName('') : $log->_filename,
      'CIVI_UF' => \CRM_Core_Config::singleton()->userFramework,
      'IS_INSTALLED' => '1',
      'SITE_TYPE' => 'cv-auto',
    );

    if (is_callable(array('Civi', 'paths'))) {
      $data += [
        'CMS_URL' => \Civi::paths()->getUrl('[cms.root]/', 'absolute'),
        'CMS_ROOT' => \Civi::paths()->getPath('[cms.root]/.'),
        'CIVI_CORE' => \Civi::paths()->getPath('[civicrm.root]/.'),
         // NOTE: not in buildkit:
        'CIVI_URL' => \Civi::paths()->getUrl('[civicrm.root]/', 'absolute'),
        'CIVI_FILES' => \Civi::paths()->getPath('[civicrm.files]/.'),
      ];
    }
    else {
      $data += [
        'CMS_URL' => \CRM_Utils_System::languageNegotiationURL(\CRM_Utils_System::baseCMSURL(), FALSE, TRUE),
        'CMS_ROOT' => \CRM_Core_Config::singleton()->userSystem->cmsRootPath(),
        'CIVI_CORE' => $GLOBALS['civicrm_root'],
        // NOTE: not in buildkit:
        'CIVI_URL' => '',
        // NOTE: This was ill-defined in older versions:
        'CIVI_FILES' => dirname(\CRM_Core_Config::singleton()->templateCompileDir),
      ];
    }

    return $data;
  }

  /**
   * @parm array $data
   * @return array
   */
  public function clean($data) {
    $this->cleanPaths($data);
    $this->cleanUrls($data);
    $this->cleanDbs($data);
    ksort($data);
    return $data;
  }

  /**
   * @param $data
   */
  protected function cleanPaths(&$data) {
    $paths = array('CIVI_CORE', 'CIVI_FILES', 'CIVI_TEMPLATEC', 'CMS_ROOT');
    foreach ($paths as $path) {
      if (!empty($data[$path])) {
        $data[$path] = rtrim($data[$path], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
      }
    }
  }

  /**
   * @param $data
   */
  protected function cleanUrls(&$data) {
    $urls = array('CMS_URL', 'CIVI_URL');
    foreach ($urls as $url) {
      if (isset($data[$url])) {
        $data[$url] = rtrim($data[$url], '/') . '/';
      }
    }
  }

  /**
   * @param $data
   */
  protected function cleanDbs(&$data) {
    foreach (array('CIVI', 'CMS', 'TEST') as $prefix) {
      if (isset($data["{$prefix}_DB_DSN"])) {
        $data = array_merge($data, $this->parseDsn($prefix, $data["{$prefix}_DB_DSN"]));
      }
    }
  }

  protected function parseDsn($prefix, $dsn) {
    $url = parse_url($dsn);

    // Ex: CMS_DB_ARGS="-h 127.0.0.1 -u username -ptopsecret -P 3307 dbname"
    $parts = array();
    if (!empty($url['host'])) {
      $parts[] = '-h';
      $parts[] = $url['host'];
    }
    if (!empty($url['user'])) {
      $parts[] = '-u';
      $parts[] = $url['user'];
    }
    if (!empty($url['pass'])) {
      $parts[] = '-p' . $url['user'];
    }
    if (!empty($url['port'])) {
      $parts[] = '-P';
      $parts[] = $url['port'];
    }
    if (!empty($url['path'])) {
      $parts[] = trim($url['path'], '/');
    }
    foreach ($parts as $part) {
      if (!preg_match('/^[a-zA-Z0-9\._+\-]*$/', $part)) {
        throw new \InvalidArgumentException("Cannot format DB CLI arguments ($dsn)");
      }
    }

    return array(
      "{$prefix}_DB_USER" => empty($url['user']) ? '' : $url['user'],
      "{$prefix}_DB_PASS" => empty($url['pass']) ? '' : $url['pass'],
      "{$prefix}_DB_HOST" => empty($url['host']) ? '' : $url['host'],
      "{$prefix}_DB_PORT" => empty($url['port']) ? '' : $url['port'],
      "{$prefix}_DB_NAME" => empty($url['path']) ? '' : trim($url['path'], '/'),
      "{$prefix}_DB_ARGS" => implode(' ', $parts),
    );
  }

}
