<?php
namespace Civi\Cv;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Bootstrap the CiviCRM runtime.
 *
 * @code
 * // Use default bootstrap
 * require_once '/path/to/Civi/Bootstrap.php';
 * Civi\Bootstrap::singleton()->boot();
 *
 * // Use custom bootstrap
 * require_once '/path/to/Civi/Bootstrap.php';
 * Civi\Bootstrap::singleton()->boot(array(
 *   'settingsFile' => '/path/to/civicrm.settings.php',
 * ));
 * @endcode
 *
 * This class is intended to be run *before* the classloader is available. Therefore, it
 * must be self-sufficient.
 *
 * A key issue is locating the civicrm.settings.php file -- this is complicated because
 * each CMS has a different structure, because some CMS's have multisite features, and
 * because we don't know who's calling us.
 *
 * By default, bootstrap will search as follows:
 *   - Check ../settings_location.php for define(CIVICRM_CONFDIR)
 *   - Check ENV['CIVICRM_SETTINGS']
 *   - Check $options['settingsFile']
 *   - Scan PWD and every ancestor directory to see if it
 *     contains civicrm.settings.php in one of the
 *     standard subdirectories.
 *
 * Recommendations:
 *   - Administrators with an unusual directory structure should either:
 *     - Set env var CIVICRM_SETTINGS in their httpd vhost and bashrc, or
 *     - Create settings_location.php
 *   - Primary CMS-integration modules should preemptively configure the
 *     defaults so that other code may bootstrap without specifying options.
 *       require_once $cividir/Civi/Bootstrap.php
 *       Civi\Bootstrap::singleton()->setOptions(
 *         'settingsFile' => ...,
 *         'search' => FALSE,
 *       ));
 *   - External scripts should call:
 *       require_once $cividir/Civi/Bootstrap.php;
 *       Civi\Bootstrap::singleton()->boot();
 *   - Administrators who are concerned about bootstrap time for external
 *     scripts should use CIVICRM_SETTINGS or settings_location.php.
 *   - Pre-installation programs (code-generators, installers, etc) should not
 *     use Civi\Bootstrap. Instead, they should use CRM_Core_ClassLoader.
 *
 * The bootstrapper accepts a few options (either via setOptions() or boot()). They are:
 *   - dynamicSettingsFile: string|NULL. The location of a PHP file which dynamically
 *     determines the location of civicrm.settings.php. This is provided for backward
 *     compatibility.
 *     (Default: $civiRoot/settings_location.php)
 *   - cmsType: string|NULL. Give a hint to the search algorithm about which
 *     type of CMS is being used.
 *     (Default: NULL)
 *   - env: string|NULL. The environment variable which may contain the path to
 *     civicrm.settings.php. Set NULL to disable.
 *     (Default: CIVICRM_SETTINGS)
 *   - prefetch: bool. Whether to load various caches.
 *     (Default: TRUE)
 *   - settingsFile: string|NULL. The full path to the civicrm.settings.php
 *     (Default: NULL)
 *   - search: bool|string. Attempt to determine root+settings by searching
 *     the file system and checking against common Civi directory structures.
 *     Boolean TRUE means it should use a default (PWD).
 *     (Default: TRUE aka PWD)
 *
 * TODO: Consider adding flags for CMS bootstrap.
 *
 * @package Civi
 */
class Bootstrap {

  const PHP_RECOMMENDED_MIN = '7.0.0';

  protected static $singleton = NULL;

  protected $options = array();

  /**
   * @var \Symfony\Component\Console\Output\OutputInterface
   *   Output mechanism for verbose boot messages.
   * @see http://symfony.com/doc/current/console/verbosity.html
   */
  protected $output = FALSE;

  /**
   * Wrapper around OutputInterface::writeln()
   *
   * @param string $text
   * @param int $level
   */
  public function writeln($text, $level = OutputInterface::VERBOSITY_NORMAL) {
    if ($this->output) {
      $this->output->writeln("<info>[Bootstrap]</info> $text", $level);
    }
  }

  /**
   * @return Bootstrap
   */
  public static function singleton() {
    if (self::$singleton === NULL) {
      self::$singleton = new Bootstrap(array(
        'dynamicSettingsFile' => dirname(__DIR__) . '/settings_location.php',
        'env' => 'CIVICRM_SETTINGS',
        'prefetch' => TRUE,
        'settingsFile' => NULL,
        'search' => TRUE,
        'cmsType' => NULL,
        'httpHost' => array_key_exists('HTTP_HOST', $_SERVER) ? $_SERVER['HTTP_HOST'] : '',
      ));
    }
    return self::$singleton;
  }

  /**
   * @param array $options
   *   See options in class doc.
   */
  public function __construct($options = array()) {
    $this->options = $options;
  }

  /**
   * Bootstrap the CiviCRM runtime.
   *
   * @param array $options
   *   See options in class doc.
   * @throws \Exception
   */
  public function boot($options = array()) {
    if (!empty($options['output'])) {
      $this->output = $options['output'];
    }

    $isBooting = TRUE;
    register_shutdown_function(function() use (&$isBooting) {
      if (!$isBooting) {
        // Someone will probably handle it
        return;
      }
      $last_error = error_get_last();
      switch ($last_error['type']) {
        case E_ERROR:
        case E_PARSE:
        case E_COMPILE_ERROR:
          $errMsg = sprintf("PHP Error: %s\nat line %s in %s", $last_error['message'], $last_error['line'], $last_error['file']);
          // We only warn about PHP version if there's actually a fatal; for older versions of civicrm-core, it would be incorrect for
          // cv to start outputting PHP version errors.
          if (!version_compare(PHP_VERSION, self::PHP_RECOMMENDED_MIN, '>=')) {
            $errMsg .= sprintf("\n\nWARNING: cv recommends PHP %s+ for use with current CiviCRM versions. This command is running PHP %s.", self::PHP_RECOMMENDED_MIN, PHP_VERSION);
          }

          if ($this->output && is_callable([$this->output, 'getErrorOutput'])) {
            $this->output->getErrorOutput()->writeln("<error>$errMsg</error>");
          }
          elseif ($this->output) {
            $this->output->writeln("<error>$errMsg</error>");
          }
          else {
            fwrite(STDERR, "$errMsg\n");
          }
          break;
      }
    });

    if (!defined('CIVICRM_SETTINGS_PATH')) {

      $this->options = $options = array_merge($this->options, $options);
      $this->writeln("Options: " . Encoder::encode($options, 'json-pretty'), OutputInterface::VERBOSITY_DEBUG);

      $this->writeln("Find settings file", OutputInterface::VERBOSITY_DEBUG);
      $settings = $this->getCivicrmSettingsPhp($options);
      if (empty($settings) || !file_exists($settings)) {
        throw new \Exception("Failed to locate civicrm.settings.php."
          . " By default, this tool searches the parent directories for a standard CMS (Drupal, WordPress, etal) and standard civicrm.settings.php."
          . " Symlinks and multisite configurations may interfere."
          . " To customize, set variable CIVICRM_SETTINGS to point to the preferred civicrm.settings.php.");
      }

      $this->writeln("Load supplemental configuration for \"$settings\"", OutputInterface::VERBOSITY_DEBUG);
      $reader = new SiteConfigReader($settings);
      $GLOBALS['_CV'] = $reader->compile(array('buildkit', 'home'));

      $this->writeln("Load settings file \"" . $settings . "\"", OutputInterface::VERBOSITY_DEBUG);
      define('CIVICRM_SETTINGS_PATH', $settings);
      $error = @include_once $settings;
      if ($error == FALSE) {
        $this->writeln("Failed to load settings file", OutputInterface::VERBOSITY_VERBOSE);
        throw new \Exception("Could not load the CiviCRM settings file: {$settings}");
      }

      $this->writeln("Find CMS root for \"" . $this->getSearchDir() . "\"", OutputInterface::VERBOSITY_VERBOSE);
      list ($cmsType, $cmsBasePath) = $this->findCmsRoot($this->getSearchDir());
      $this->writeln("Found \"$cmsType\" in \"$cmsBasePath\"", OutputInterface::VERBOSITY_VERBOSE);

      if (PHP_SAPI === "cli") {
        $this->writeln("Simulate web environment in CLI", OutputInterface::VERBOSITY_VERBOSE);
        $_SERVER['SCRIPT_FILENAME'] = $cmsBasePath . '/index.php';
        $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
        $_SERVER['SERVER_SOFTWARE'] = NULL;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        if (ord($_SERVER['SCRIPT_NAME']) != 47) {
          $_SERVER['SCRIPT_NAME'] = '/' . $_SERVER['SCRIPT_NAME'];
        }
      }
    }

    // Backward compatibility - New civicrm.settings.php files include
    // the classloader, but old ones don't.
    $this->writeln("Initialize class loader", OutputInterface::VERBOSITY_VERBOSE);
    global $civicrm_root;
    require_once $civicrm_root . '/CRM/Core/ClassLoader.php';
    \CRM_Core_ClassLoader::singleton()->register();

    if (!empty($options['prefetch'])) {
      $this->writeln("Call core bootstrap", OutputInterface::VERBOSITY_VERBOSE);
      // I'm not sure why this is called explicitly during bootstrap
      // rather than lazily. However, it seems to be done by all
      // the existing bootstrap code. Perhaps initializing Config
      // has a side-effect of initializing other things?
      \CRM_Core_Config::singleton();
    }
    $this->writeln("Finished", OutputInterface::VERBOSITY_DEBUG);
    $isBooting = FALSE;
  }

  /**
   * Generate bootstrap logic.
   *
   * NOTE: Assumes boot() has already run.
   *
   * @return string
   *   PHP code.
   */
  public function generate() {
    $code = array();

    $code[] = 'if (PHP_SAPI === "cli") {';
    $srvVars = array(
      'SCRIPT_FILENAME',
      'REMOTE_ADDR',
      'SERVER_SOFTWARE',
      'REQUEST_METHOD',
      'SCRIPT_NAME',
    );
    foreach ($srvVars as $srvVar) {
      $code[] = sprintf('$_SERVER["%s"] = %s;',
        $srvVar, var_export($_SERVER[$srvVar], 1));
    }
    foreach (array('CIVICRM_UF') as $envVar) {
      if (getenv($envVar)) {
        $code[] = sprintf('putenv("%s=" . %s);', $envVar, var_export(getenv($envVar), 1));
        $code[] = sprintf('$_ENV["%s"] = %s;', $envVar, var_export(getenv($envVar), 1));
      }
    }
    $code[] = '}';

    $code[] = sprintf('$GLOBALS[\'_CV\'] = %s;', var_export($GLOBALS['_CV'], 1));

    $code[] = sprintf('define("CIVICRM_SETTINGS_PATH", %s);', var_export(CIVICRM_SETTINGS_PATH, 1));
    $code[] = '$error = @include_once CIVICRM_SETTINGS_PATH;';
    $code[] = 'if ($error == FALSE) {';
    $code[] = '  throw new \Exception("Could not load the CiviCRM settings file: {$settings}");';
    $code[] = '}';

    $code[] = 'require_once $GLOBALS["civicrm_root"] . "/CRM/Core/ClassLoader.php";';
    $code[] = '\CRM_Core_ClassLoader::singleton()->register();';

    return implode("\n", $code);
  }

  /**
   * @return array
   *   See options in class doc.
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * @param array $options
   *   See options in class doc.
   */
  public function setOptions($options) {
    $this->options = $options;
  }

  /**
   * @param array $options
   * @return string
   * @throws \Exception
   */
  public function getCivicrmSettingsPhp($options) {
    if (!empty($options['dynamicSettingsFile']) && file_exists($options['dynamicSettingsFile'])) {
      include $options['dynamicSettingsFile'];
    }

    /**
     * @var string
     *   Path to the settings file.
     */
    $settings = NULL;

    if (defined('CIVICRM_CONFDIR') && file_exists(CIVICRM_CONFDIR . '/civicrm.settings.php')) {
      $settings = CIVICRM_CONFDIR . '/civicrm.settings.php';
    }
    elseif (!empty($options['env']) && getenv($options['env']) && file_exists(getenv($options['env']))) {
      $settings = getenv($options['env']);
    }
    elseif (!empty($options['settingsFile']) && file_exists($options['settingsFile'])) {
      $settings = $options['settingsFile'];
    }
    elseif (!empty($options['search'])) {
      list (, , $settings) = $this->findCivicrmSettingsPhp($this->getSearchDir());
    }

    return $settings;
  }

  /**
   * Locate a civicrm.settings.php using normal directory structures.
   *
   * @param string $searchDir
   *   The directory from which to begin the upward search.
   * @return array
   *   Array(string $cmsType, string $cmsRoot, string $settingsFile).
   */
  protected function findCivicrmSettingsPhp($searchDir) {
    list ($cmsType, $cmsRoot) = $this->findCmsRoot($searchDir);
    $settings = NULL;
    switch ($cmsType) {
      case 'backdrop':
        $settings = $this->findFirstFile(
           array_merge($this->findDrupalDirs($cmsRoot), array($cmsRoot)),
          'civicrm.settings.php'
        );
        break;

      case 'drupal':
        $settings = $this->findFirstFile($this->findDrupalDirs($cmsRoot), 'civicrm.settings.php');
        break;

      case 'joomla':
        // Note: Joomla technically has two copies of civicrm.settings.php with
        // slightly different values of CIVICRM_UF_BASEURL. It appears that Joomla
        // always used the admin copy for CLI/bin/extern scripts, so we do the
        // same. However, the arrangement seems gratuitous considering that WP
        // has the same frontend/backend split and does not need two copies of
        // civicrm.settings.php.

        $settings = $cmsRoot . '/administrator/components/com_civicrm/civicrm.settings.php';
        // $result =  $cmsRoot . 'components/com_civicrm/civicrm.settings.php';
        break;

      case 'wp':
        $wpDirs = array(
          $cmsRoot . '/*/uploads/civicrm',
          $cmsRoot . '/*/plugins/civicrm',
          $cmsRoot . '/*/uploads/sites/*/civicrm',
          $cmsRoot . '/*/blogs.dir/*/files/civicrm',
        );
        $settings = $this->findFirstFile($wpDirs, 'civicrm.settings.php');
        break;
    }
    return array($cmsType, $cmsRoot, $settings);
  }

  /**
   * Get an ordered list of multisite dirs that might apply to this request.
   *
   * @param string $cmsRoot
   *   The root of the Drupal installation.
   * @return array
   */
  protected function findDrupalDirs($cmsRoot) {
    $sites = array();
    if (file_exists("$cmsRoot/sites/sites.php")) {
      include("$cmsRoot/sites/sites.php");
    }
    $dirs = array();
    $server = explode('.', implode('.', array_reverse(explode(':', rtrim($this->options['httpHost'], '.')))));
    for ($j = count($server); $j > 0; $j--) {
      $s = implode('.', array_slice($server, -$j));
      if (isset($sites[$s]) && file_exists("$cmsRoot/sites/" . $sites[$s])) {
        $s = $sites[$s];
      }
      $dirs[] = "$cmsRoot/sites/$s";
    }
    $dirs[] = "$cmsRoot/sites/default";
    return $dirs;
  }

  /**
   * @param string $searchDir
   *   The directory from which to begin the upward search.
   * @return array
   *   Array(string $cmsType, string $cmsRoot, string $civiRoot)
   */
  protected function findCmsRoot($searchDir) {
    // A list of file patterns; if one of the patterns matches a give
    // directory, then we can assume that this directory is the
    // CMS root.
    $cmsPatterns = array(
      'wp' => array(
        'wp-includes/version.php',
        // Future? 'vendor/civicrm/wordpress/civicrm.php' => 'wp',
      ),
      'joomla' => array(
        'administrator/components/com_civicrm/civicrm/civicrm-version.php',
        'administrator/components/com_users/users.php',
        // Future? 'vendor/civicrm/joomla/civicrm.php' => 'joomla',
      ),
      'drupal' => array(
        // D7
        'modules/system/system.module',
        // D8
        'core/core.services.yml',
      ),
      'backdrop' => array(
        'core/modules/layout/layout.module',
      ),
    );

    $parts = explode('/', str_replace('\\', '/', $searchDir));
    while (!empty($parts)) {
      $basePath = implode('/', $parts);

      foreach ($cmsPatterns as $cmsType => $relPaths) {
        if (!empty($this->options['cmsType']) && $this->options['cmsType'] != $cmsType) {
          continue;
        }
        foreach ($relPaths as $relPath) {
          $matches = glob("$basePath/$relPath");
          if (!empty($matches)) {
            return array($cmsType, $basePath);
          }
          $matches = glob("$basePath/web/$relPath");
          if (!empty($matches)) {
            return array($cmsType, "$basePath/web");
          }
        }
      }
      array_pop($parts);
    }

    return array(NULL, NULL);
  }

  /**
   * @param string|array $dirs
   *   List of directories to check.
   * @param string|array $items
   *   List of globs to check in each directory.
   * @return null|string
   */
  protected function findFirstFile($dirs, $items) {
    $dirs = (array) $dirs;
    $items = (array) $items;
    foreach ($dirs as $dir) {
      foreach ($items as $item) {
        $matches = (array) glob("$dir/$item");
        if (isset($matches[0])) {
          return $matches[0];
        }
      }
    }
    return NULL;
  }

  /**
   * @return string
   */
  protected function getSearchDir() {
    if ($this->options['search'] === TRUE) {
      // exec(pwd) works better with symlinked source trees, but it's
      // probably not portable to Windows.
      if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return getcwd();
      }
      else {
        exec('pwd', $output);
        return trim(implode("\n", $output));
      }
    }
    else {
      return $this->options['search'];
    }
  }

}
