<?php
namespace Civi\Cv;

use Civi\Cv\Util\SimulateWeb;

/**
 * Bootstrap the CMS runtime.
 *
 * @code
 * // Use default bootstrap
 * require_once '/path/to/CmsBootstrap.php';
 * Civi\Cv\CmsBootstrap::singleton()->bootCms()->bootCivi();
 *
 * // Use custom bootstrap
 * require_once '/path/to/CmsBootstrap.php';
 * Civi\Cv\CmsBootstrap::singleton()
 *   ->addOptions(array(...))
 *   ->bootCms()
 *   ->bootCivi();
 * @endcode
 *
 * This class is intended to be run *before* the classloader is available. Therefore, it
 * must be self-sufficient - do not rely on other classes, even in the same package.
 *
 * By default, bootstrap will scan PWD and every ancestor directory to see if it
 * contains a supported CMS. If you have performance considerations, or if the
 * directory structure is not amenable to scanning, then set the environment
 * variable CIVICRM_BOOT (in bashrc and/or httpd vhost).
 *
 * The bootstrapper accepts a few options (either via the constructor or addOptions()). They are:
 *   - env: string|NULL. The environment variable which may contain boot options
 *     Ex: 'Drupal://var/www' or 'WordPress://admin@var/www'. Set NULL to disable.
 *     (Default: CIVICRM_BOOT)
 *   - search: bool|string. Attempt to determine root+settings by searching
 *     the file system and checking against common Civi directory structures.
 *     Boolean TRUE means it should use a default (PWD).
 *     (Default: TRUE aka PWD)
 *   - user: string|NULL. The name of a CMS user to authenticate as.
 *   - url: string|NULL. Specify the logical URL being used to process this request
 *   - httpHost: string|NULL. Specify the logical URL being used to process this request (DEPRECATED; prefer "url")
 *   - log: \Psr\Log\LoggerInterface|\Civi\Cv\Log\InternalLogger (If given, send log messages here)
 *   - output: Symfony OutputInterface. (Fallback for handling logs - in absence of 'log')
 *
 * @package Civi
 */
class CmsBootstrap {

  protected static $singleton = NULL;

  protected $options = array();

  /**
   * @var \Psr\Log\LoggerInterface|\Civi\Cv\Log\InternalLogger
   */
  protected $log = NULL;

  /**
   * @var array|null
   */
  protected $bootedCms = NULL;

  /**
   * Holds the requested user to login as for Standalone.
   * Normally the user is loaded in bootCms but since Civi is our CMS,
   * we have to wait for bootCivi().
   *
   * @var string|null
   */
  protected $deferredUserToLogin = NULL;

  /**
   * @param string $text
   * @param int $level
   * @deprecated
   */
  public function writeln($text, $level = 32) {
    $this->log->info($text);
  }

  /**
   * @return CmsBootstrap
   */
  public static function singleton() {
    if (self::$singleton === NULL) {
      self::$singleton = new CmsBootstrap(array(
        'env' => 'CIVICRM_BOOT',
        'search' => TRUE,
        'url' => SimulateWeb::detectEnvUrl(),
        'user' => NULL,
      ));
    }
    return self::$singleton;
  }

  /**
   * @param array $options
   *   See options in class doc.
   */
  public function __construct($options = array()) {
    $this->addOptions($options);
  }

  /**
   * Export bootstrap logic.
   *
   * @param array $actions
   *   List of bootstrap actions to include.
   *   Ex: ['bootCms', 'bootCivi']
   * @return string
   *   PHP code to `CmsBootstrap` in a new process
   */
  public function generate(array $actions = []): string {
    $instanceExpr = '\\' . get_class($this) . '::singleton()';
    $code = '';
    $code .= sprintf("require_once %s;\n", var_export(CV_AUTOLOAD, TRUE));
    $code .= sprintf("%s->addOptions(%s);\n", $instanceExpr, var_export($this->getOptions(), TRUE));
    foreach ($actions as $action) {
      $code .= sprintf("%s->%s();\n", $instanceExpr, $action);
    }
    return $code;
  }

  /**
   * Bootstrap the CiviCRM runtime.
   *
   * @return CmsBootstrap
   * @throws \Exception
   */
  public function bootCms() {
    $this->log->debug("Options: " . json_encode($this->options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    if ($this->options['env'] && getenv($this->options['env'])) {
      $cmsExpr = getenv($this->options['env']);
      $this->log->debug("Parse CMS options ($cmsExpr)");
      $cms = array(
        'type' => parse_url($cmsExpr, PHP_URL_SCHEME),
        'path' => '/' . parse_url($cmsExpr, PHP_URL_HOST) . parse_url($cmsExpr, PHP_URL_PATH),
      );
      $cms['path'] = preg_replace(';^//+;', '/', $cms['path']);
      if ($cms['type'] === 'Auto') {
        $isAutoPath = (trim($cms['path'], '/') === '.');
        $cms = $this->findCmsRoot($isAutoPath ? $this->getSearchDir() : $cms['path']);
      }
      if (!isset($this->options['user']) && parse_url($cmsExpr, PHP_URL_USER)) {
        $this->options['user'] = parse_url($cmsExpr, PHP_URL_USER);
      }
      if (parse_url($cmsExpr, PHP_URL_QUERY)) {
        parse_str(parse_url($cmsExpr, PHP_URL_QUERY), $query);
        if (!empty($query['host'])) {
          $this->options['url'] = $query['host'];
        }
      }
    }
    else {
      $this->log->debug("Find CMS...");
      $cms = $this->findCmsRoot($this->getSearchDir());
    }

    $this->log->debug("CMS: " . json_encode($cms, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if (empty($cms['path']) || empty($cms['type']) || !file_exists($cms['path'])) {
      $cmsJson = json_encode($cms, JSON_UNESCAPED_SLASHES);
      throw new \Exception("Failed to parse or find a CMS $cmsJson");
    }

    if (PHP_SAPI === "cli") {
      $this->log->debug("Simulate web environment in CLI");
      SimulateWeb::apply($this->options['url'] ?? SimulateWeb::localhost(),
        $cms['path'] . '/index.php',
        ($cms['type'] === 'Drupal') ? NULL : ''
      );
    }

    $originalErrorReporting = error_reporting();
    $func = 'boot' . $cms['type'];
    if (!is_callable([$this, $func])) {
      throw new \Exception("Failed to locate boot function ($func)");
    }

    call_user_func([$this, $func], $cms['path'], $this->options['user']);

    if (PHP_SAPI === "cli") {
      error_reporting($originalErrorReporting);
    }

    $this->log->debug("Finished");
    $this->bootedCms = $cms;
    return $this;
  }

  /**
   * Determine what, if any, CMS has been bootstrapped.
   *
   * @return string|NULL
   *   Ex: 'Backdrop', 'Drupal', 'Drupal8', 'Joomla', 'WordPress'.
   */
  public function getBootedCmsType() {
    return isset($this->bootedCms['type']) ? $this->bootedCms['type'] : NULL;
  }

  /**
   * Determine what, if any, CMS has been bootstrapped.
   *
   * @return string|NULL
   *   Ex: '/var/www'.
   */
  public function getBootedCmsPath() {
    return isset($this->bootedCms['path']) ? $this->bootedCms['path'] : NULL;
  }

  /**
   * @return $this
   * @throws \Exception
   */
  public function bootCivi() {
    // PRE-CONDITIONS: CMS has already been booted, and Civi is already installed.
    if (function_exists('civicrm_initialize')) {
      civicrm_initialize();
    }
    elseif (class_exists('Drupal')) {
      //Drupal 8 / 9
      \Drupal::service('civicrm')->initialize();
    }
    elseif ($this->bootedCms['type'] === 'Standalone') {
      $this->log->debug("Hello there Standalone, come join us!");
      \Civi\Cv\Bootstrap::singleton()->boot();
      $this->loginStandaloneUser();
    }
    elseif ($this->bootedCms['type'] === 'Joomla') {
      if (!defined('CIVICRM_SETTINGS_PATH')) {
        define('CIVICRM_SETTINGS_PATH', JPATH_BASE . '/components/com_civicrm/civicrm.settings.php');
      }
      require_once CIVICRM_SETTINGS_PATH;
      require_once 'CRM/Core/Config.php';
      $config = \CRM_Core_Config::singleton();
      \CRM_Utils_Hook::config($config, ['uf' => TRUE]);
      $app = \Joomla\CMS\Factory::getApplication();
      $joomlaConfig = $app->getConfig();
      $timezone = $joomlaConfig->get('offset');
      if ($timezone && is_callable([$config->userSystem, 'setTimeZone'])) {
        $config->userSystem->setTimeZone($timezone);
      }
      elseif ($timezone) {
        date_default_timezone_set($timezone);
      }
    }
    else {
      throw new \Exception("This system does not appear to have CiviCRM");
    }

    if (!empty($this->options['user'])) {
      $this->ensureUserContact();
    }

    // Some UF integrations/versions don't seem to do this... work-around...
    if (is_callable([\CRM_Core_Config::singleton()->userSystem, 'setMySQLTimeZone'])) {
      \CRM_Core_Config::singleton()->userSystem->setMySQLTimeZone();
    }

    $GLOBALS['_CV'] = $this->buildCv();

    return $this;
  }

  protected function buildCv(): array {
    $settings = constant('CIVICRM_SETTINGS_PATH');
    if ($settings && class_exists('Civi\Cv\SiteConfigReader')) {
      $this->log->debug("Load supplemental configuration for \"$settings\"");
      $reader = new SiteConfigReader($settings);
      return $reader->compile(array('buildkit', 'home'));
    }
    else {
      $this->log->debug("Warning: Not loading supplemental configuration for \"$settings\". SiteConfigReader is missing.");
      return [];
    }
  }

  /**
   */
  protected function loginStandaloneUser() {
    if (!empty($this->deferredUserToLogin)) {
      global $loggedInUserId;
      if (class_exists(\Civi\Api4\User::class)) {
        $userID = \Civi\Api4\User::get(FALSE)
          ->addWhere('username', '=', $this->deferredUserToLogin)
          ->addWhere('is_active', '=', 1)
          ->execute()->single();
        \CRM_Core_Session::singleton()->set('ufId', $userID);
        $loggedInUserId = $userID['contact_id'];
      }
      if (empty($loggedInUserId)) {
        throw new \RuntimeException("Unable to login as '$this->deferredUserToLogin'");
      }
    }
  }

  public function bootBackdrop($cmsPath, $cmsUser) {
    if (!file_exists("$cmsPath/core/includes/bootstrap.inc")) {
      throw new \Exception('Sorry, could not locate Backdrop\'s bootstrap.inc');
    }
    chdir($cmsPath);
    define('BACKDROP_ROOT', $cmsPath);
    require_once "$cmsPath/core/includes/bootstrap.inc";
    require_once "$cmsPath/core/includes/config.inc";
    \backdrop_bootstrap(BACKDROP_BOOTSTRAP_FULL);

    if (!function_exists('module_exists')) {
      throw new \Exception('Sorry, could not bootstrap Backdrop.');
    }

    if ($cmsUser) {
      global $user;
      $user = \user_load(array('name' => $cmsUser));
    }

    return $this;
  }

  public function bootDrupal($cmsPath, $cmsUser) {
    if (!file_exists("$cmsPath/includes/bootstrap.inc")) {
      // Sanity check.
      throw new \Exception('Sorry, could not locate Drupal\'s bootstrap.inc');
    }
    chdir($cmsPath);
    define('DRUPAL_ROOT', $cmsPath);
    require_once 'includes/bootstrap.inc';
    \drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

    if (!function_exists('module_exists')) {
      throw new \Exception('Sorry, could not bootstrap Drupal.');
    }

    if ($cmsUser) {
      global $user;
      $user = \user_load_by_name($cmsUser);
    }

    return $this;
  }

  public function bootDrupal8($cmsRootPath, $cmsUser) {
    if (!file_exists("$cmsRootPath/core/core.services.yml")) {
      // Sanity check.
      throw new \Exception('Sorry, could not locate Drupal8\'s core.services.yml');
    }

    chdir($cmsRootPath);
    define('DRUPAL_DIR', $cmsRootPath);
    $autoloader = require_once DRUPAL_DIR . '/autoload.php';
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $kernel = \Drupal\Core\DrupalKernel::createFromRequest($request, $autoloader, 'prod');
    $kernel->boot();
    $kernel->preHandle($request);
    $container = $kernel->rebuildContainer();
    // Add our request to the stack and route context.
    $request->attributes->set(\Drupal\Core\Routing\RouteObjectInterface::ROUTE_OBJECT, new \Symfony\Component\Routing\Route('<none>'));
    $request->attributes->set(\Drupal\Core\Routing\RouteObjectInterface::ROUTE_NAME, '<none>');
    $container->get('request_stack')->push($request);
    $container->get('router.request_context')->fromRequest($request);

    if (!function_exists('t')) {
      throw new \Exception('Sorry, could not bootstrap Drupal8.');
    }

    if ($cmsUser) {
      // Drupal 8
      if (method_exists('Drupal', 'entityManager')) {
        $entity_manager = \Drupal::entityManager();
        $users = $entity_manager->getStorage($entity_manager->getEntityTypeFromClass('Drupal\user\Entity\User'))
          ->loadByProperties(array(
            'name' => $cmsUser,
          ));
      }
      else {
        // Drupal 9
        $entity_manager = \Drupal::entityTypeManager();
        $users = $entity_manager->getStorage(\Drupal::service('entity_type.repository')->getEntityTypeFromClass('Drupal\user\Entity\User'))
          ->loadByProperties([
            'name' => $cmsUser,
          ]);
      }
      if (count($users) == 1) {
        foreach ($users as $uid => $user) {
          user_login_finalize($user);
        }
      }
      elseif (empty($users)) {
        throw new \Exception(sprintf("Failed to find Drupal8 user (%s)", $cmsUser));
      }
      else {
        throw new \Exception(sprintf("Found too many Drupal8 users (%d)", count($users)));
      }
    }

    return $this;
  }

  public function bootJoomla($cmsRootPath, $cmsUser) {
    $cmsRootPath = rtrim($cmsRootPath, '/');
    $cmsUser = $cmsUser;
    define('_JEXEC', 1);
    define('DS', DIRECTORY_SEPARATOR);
    define('JPATH_BASE', $cmsRootPath . DS . 'administrator');
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';
    $container = \Joomla\CMS\Factory::getContainer();
    $container->alias('session', 'session.cli')
      ->alias('JSession', 'session.cli')
      ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
      ->alias(\Joomla\Session\Session::class, 'session.cli')
      ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
    $app = $container->get(\Joomla\CMS\Application\ConsoleApplication::class);
    \Joomla\CMS\Factory::$application = $app;
    if ($cmsUser) {
      $userFactory = \Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class);
      $user = $userFactory->loadUserByUserName($cmsUser);
      if (empty($user->id)) {
        throw new \Exception(sprintf("Fail to find Joomla user (%s)", $cmsUser));
      }
    }
    return $this;
  }

  /**
   * @param string $cmsRootPath
   * @param string $cmsUser
   * @return $this
   */
  public function bootWordPress($cmsRootPath, $cmsUser) {
    if (!file_exists($cmsRootPath . DIRECTORY_SEPARATOR . 'wp-load.php')) {
      throw new \Exception('Sorry, could not locate WordPress\'s wp-load.php.');
    }
    chdir($cmsRootPath);
    require_once $cmsRootPath . DIRECTORY_SEPARATOR . 'wp-load.php';

    if (!function_exists('wp_set_current_user')) {
      throw new \Exception('Sorry, could not bootstrap WordPress.');
    }

    if ($cmsUser) {
      wp_set_current_user(NULL, $cmsUser);
    }

    return $this;
  }

  /**
   * @param string $cmsPath
   * @param string $cmsUser
   * @return $this
   */
  public function bootStandalone($cmsPath, $cmsUser) {
    /* @todo clarify: assumes $cmsPath is to the project root, not the webroot */
    $candidates = [
      $cmsPath . '/vendor/autoload.php',
      $cmsPath . '/core/vendor/autoload.php',
      $cmsPath . '/web/core/vendor/autoload.php',
      dirname($cmsPath) . '/vendor/autoload.php',
    ];
    $autoloader = NULL;
    foreach ($candidates as $candidate) {
      if (file_exists($candidate)) {
        $autoloader = $candidate;
        break;
      }
    }

    if (!$autoloader) {
      throw new \RuntimeException("Failed to find autoloader. Possibilities: " . implode(', ', $candidates));
    }
    require_once $autoloader;

    $this->deferredUserToLogin = $cmsUser ?? NULL;
    return $this;
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
   * @return CmsBootstrap
   */
  public function addOptions($options) {
    if (isset($options['httpHost'])) {
      $options['url'] = $options['url'] ?? $options['httpHost'];
      unset($options['httpHost']);
    }
    if (isset($options['url'])) {
      $options['url'] = SimulateWeb::prependDefaultScheme($options['url']);
    }

    $this->options = array_merge($this->options, $options);
    $this->log = Log\Logger::resolve($options, 'CmsBootstrap');
    return $this;
  }

  /**
   * @param string $searchDir
   *   The directory from which to begin the upward search.
   * @return array|NULL
   *   Ex: ['path' => '/var/www', 'type' => 'WordPress]
   */
  protected function findCmsRoot($searchDir) {
    // A list of file patterns; if one of the patterns matches a give
    // directory, then we can assume that this directory is the
    // CMS root.
    $cmsPatterns = array(
      'WordPress' => array(
        // 'wp-includes/version.php',
        'wp-load.php',
      ),
      'Joomla' => array(
        'administrator/components/com_users/users.php',
        'libraries/src/Factory.php',
      ),
      'Drupal' => array(
        'modules/system/system.module',
      ),
      'Drupal8' => array(
        'core/core.services.yml',
      ),
      'Backdrop' => array(
        'core/modules/layout/layout.module',
      ),
      'Standalone' => array(
        'civicrm.standalone.php',
        'civicrm.config.php.standalone',
        // or?
        // 'data/civicrm.settings.php',
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
            return array('path' => $basePath, 'type' => $cmsType);
          }
          $matches = glob("$basePath/web/$relPath");
          if (!empty($matches)) {
            return array('path' => "$basePath/web", 'type' => $cmsType);
          }
          if ($cmsType === 'Standalone') {
            $matches = glob("$basePath/srv/$relPath");
            if (!empty($matches)) {
              return array('path' => "$basePath/srv", 'type' => $cmsType);
            }
          }
        }
      }

      array_pop($parts);
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

  protected function ensureUserContact() {
    if ($cid = \CRM_Core_Session::getLoggedInContactID()) {
      return $cid;
    }

    // Ugh, this codepath.
    switch (CIVICRM_UF) {
      case 'Drupal':
      case 'Drupal6':
      case 'Backdrop':
        \CRM_Core_BAO_UFMatch::synchronize($GLOBALS['user'], TRUE, CIVICRM_UF, 'Individual');
        break;

      case 'Drupal8':
        $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
        \CRM_Core_BAO_UFMatch::synchronize($user, TRUE, CIVICRM_UF, 'Individual');
        break;

      case 'Joomla':
        $user = (!class_exists('JFactory') ? \Joomla\CMS\Factory::getUser() : \JFactory::getUser());
        \CRM_Core_BAO_UFMatch::synchronize($user, TRUE, CIVICRM_UF, 'Individual');
        break;

      case 'WordPress':
        \CRM_Core_BAO_UFMatch::synchronize($GLOBALS['current_user'], TRUE, CIVICRM_UF, 'Individual');
        break;

      default:
        $this->log->error("Unrecognized UF: " . CIVICRM_UF);
    }

    return \CRM_Core_Session::getLoggedInContactID();
  }

}
