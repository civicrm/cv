<?php
namespace Civi\Cv\Util;

use Civi\Cv\ErrorHandler;
use Civi\Cv\Log\InternalLogger;
use Civi\Cv\Log\SymfonyConsoleLogger;
use Civi\Cv\PharOut\PharOut;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This trait can be mixed into a Symfony `Command` to provide Civi/CMS-bootstrap options.
 *
 * It first boots Civi and then boots the CMS.
 */
trait BootTrait {

  /**
   * Describe the expected bootstrap behaviors for this command.
   *
   * - For most commands, you will want to automatically boot CiviCRM/CMS.
   *   The default implementation will do this.
   * - For some special commands (e.g. core-installer or PHP-script-runner), you may
   *   want more fine-grained control over when/how the system boots.
   *
   * @var array
   */
  protected $bootOptions = [
    // Whether to automatically boot Civi during `initialize()` phase.
    'auto' => TRUE,

    // Default boot level.
    'default' => 'full|cms-full',

    // List of all boot levels that are allowed in this command.
    'allow' => ['full|cms-full', 'full', 'cms-full', 'settings', 'classloader', 'cms-only', 'none'],
  ];

  /**
   * @internal
   */
  public function mergeDefaultBootDefinition($definition, $defaultLevel = 'full|cms-full') {
    // If we were only dealing with built-in/global commands, then these options could be defined at the command-level.
    // However, we also have extension-based commands. The system will boot before we have a chance to discover them.
    // By putting these options at the application level, we ensure they will be defined+used.
    $definition->addOption(new InputOption('level', NULL, InputOption::VALUE_REQUIRED, 'Bootstrap level (none,classloader,settings,full,cms-only,cms-full)', $defaultLevel));
    $definition->addOption(new InputOption('url', 'l', InputOption::VALUE_REQUIRED, 'URL or hostname of the current site (for a multisite system)'));
    $definition->addOption(new InputOption('test', 't', InputOption::VALUE_NONE, 'Bootstrap the test database (CIVICRM_UF=UnitTests)'));
    $definition->addOption(new InputOption('user', 'U', InputOption::VALUE_REQUIRED, 'CMS user'));
  }

  /**
   * @internal
   */
  public function mergeBootDefinition($definition) {
    $bootOptions = $this->getBootOptions();
    $definition->getOption('level')->setDefault($bootOptions['default']);
  }

  /**
   * Evaluate the $bootOptions.
   *
   * - If we've already booted, do nothing.
   * - If the configuration looks reasonable and if we haven't booted yet, then boot().
   * - If the configuration looks unreasonable, then abort.
   */
  protected function autoboot(InputInterface $input, OutputInterface $output): void {
    $bootOptions = $this->getBootOptions();
    if (!in_array($input->getOption('level'), $bootOptions['allow'])) {
      throw new \LogicException(sprintf("Command called with with level (%s) but only accepts levels (%s)",
        $input->getOption('level'), implode(', ', $bootOptions['allow'])));
    }

    if (!$this->isBooted() && ($bootOptions['auto'] ?? TRUE)) {
      $this->boot($input, $output);
    }
  }

  /**
   * Start CiviCRM and/or CMS. Respect options like --user and --level.
   */
  public function boot(InputInterface $input, OutputInterface $output) {
    $logger = $this->bootLogger($output);
    $logger->debug('Start');

    $this->setupErrorHandling($output);

    if ($input->hasOption('test') && $input->getOption('test')) {
      $logger->debug('Use test mode');
      putenv('CIVICRM_UF=UnitTests');
      $_ENV['CIVICRM_UF'] = 'UnitTests';
    }

    if ($input->getOption('level') === 'full|cms-full') {
      if (getenv('CIVICRM_UF') === 'UnitTests') {
        $input->setOption('level', 'full');
      }
      elseif (getenv('CIVICRM_BOOT')) {
        $input->setOption('level', 'cms-full');
      }
      elseif (getenv('CIVICRM_SETTINGS')) {
        $input->setOption('level', 'full');
      }
      else {
        $input->setOption('level', 'full');
        // TODO (when tests pass, for v0.4): $input->setOption('level', 'cms-full');
      }
    }

    if (getenv('CIVICRM_UF') === 'UnitTests' && preg_match('/^cms-/', $input->getOption('level'))) {
      throw new \Exception("UnitTest bootstrapping is not compatible with CMS bootstrapping");
    }

    $func = '_boot_' . strtr($input->getOption('level'), '-', '_');

    if (is_callable([$this, $func])) {
      call_user_func([$this, $func], $input, $output);
    }
    else {
      throw new \Exception("Unrecognized bootstrap level");
    }

    // CMS may have installed wonky error-handling. Add our own.
    ErrorHandler::pushHandler();

    $logger->debug('Finished');
  }

  /**
   * Do not do anything for bootstrap. Just use the given environment.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function _boot_none(InputInterface $input, OutputInterface $output) {
    $this->bootLogger($output)->debug('Skip');
  }

  /**
   * Setup the CiviCRM classloader.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function _boot_classloader(InputInterface $input, OutputInterface $output) {
    $this->bootLogger($output)->debug('Call basic cv bootstrap (' . $input->getOption('level') . ')');
    \Civi\Cv\Bootstrap::singleton()->boot($this->createBootParams($input, $output) + array(
      'prefetch' => FALSE,
    ));
  }

  /**
   * Get the CiviCRM classloader and settings file.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function _boot_settings(InputInterface $input, OutputInterface $output) {
    $this->bootLogger($output)->debug('Call basic cv bootstrap (' . $input->getOption('level') . ')');

    \Civi\Cv\Bootstrap::singleton()->boot($this->createBootParams($input, $output) + array(
      'prefetch' => FALSE,
    ));
  }

  /**
   * Boot CiviCRM, then boot the corresponding CMS.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @throws \Exception
   */
  public function _boot_full(InputInterface $input, OutputInterface $output) {
    PharOut::prepare();

    $logger = $this->bootLogger($output);
    $logger->debug('Call standard cv bootstrap');

    \Civi\Cv\Bootstrap::singleton()->boot($this->createBootParams($input, $output));

    $logger->debug('Call core bootstrap');

    \CRM_Core_Config::singleton();

    $logger->debug('Call CMS bootstrap');

    \CRM_Utils_System::loadBootStrap(array(), FALSE);

    if ($input->getOption('user')) {
      $logger->debug('Set system user');

      if (is_callable(array(\CRM_Core_Config::singleton()->userSystem, 'loadUser'))) {
        if (!\CRM_Core_Config::singleton()->userSystem->loadUser($input->getOption('user')) || !$this->ensureUserContact($output)) {
          throw new \Exception("Failed to determine contactID for user=" . $input->getOption('user'));
        }
      }
      else {
        $output->getErrorOutput()->writeln("<error>Failed to set user. Feature not supported by UF (" . CIVICRM_UF . ")</error>");
      }
    }

    // setTimeZone is preferred to ensure PHP and MySQL timezones are in sync on 5.80+
    // setMySQLTimeZone is retained for pre-existing behaviour on earlier versions
    // @see https://github.com/civicrm/civicrm-core/pull/31225
    if (is_callable([\CRM_Core_Config::singleton()->userSystem, 'setTimeZone'])) {
      $logger->debug('Set active timezone in MySQL / PHP');

      \CRM_Core_Config::singleton()->userSystem->setTimeZone();
    }
    elseif (is_callable([\CRM_Core_Config::singleton()->userSystem, 'setMySQLTimeZone'])) {
      $logger->debug('Set active MySQL timezone');

      \CRM_Core_Config::singleton()->userSystem->setMySQLTimeZone();
    }

    if (CIVICRM_UF === 'Joomla') {
      PharOut::reset();
    }
  }

  /**
   * Boot only the CMS.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Civi\Cv\CmsBootstrap
   */
  public function _boot_cms_only(InputInterface $input, OutputInterface $output) {
    $bootstrap = $this->createCmsBootstrap($input, $output);
    $this->bootLogger($output)->debug('Call CMS bootstrap');

    $bootstrap->bootCms();
    return $bootstrap;
  }

  /**
   * Boot the CMS, then boot CiviCRM.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Civi\Cv\CmsBootstrap
   */
  public function _boot_cms_full(InputInterface $input, OutputInterface $output) {
    $bootstrap = $this->_boot_cms_only($input, $output);
    $this->bootLogger($output)->debug('Call Civi bootstrap');

    $bootstrap->bootCivi();
    return $bootstrap;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Civi\Cv\CmsBootstrap
   */
  protected function createCmsBootstrap(InputInterface $input, OutputInterface $output) {
    if ($output->isDebug()) {
      // add the output object to allow the bootstrapper to output debug messages
      // and track verboisty
      $boot_params = array('output' => $output);
    }
    else {
      $boot_params = array();
    }

    if ($input->getOption('user')) {
      $boot_params['user'] = $input->getOption('user');
    }
    if ($input->getOption('url')) {
      $boot_params['url'] = $input->getOption('url');
    }

    return \Civi\Cv\CmsBootstrap::singleton()->addOptions($boot_params);
  }

  /**
   * Ensure that the current user has a contact record.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return int|NULL
   *   The user's contact ID, or NULL
   */
  public function ensureUserContact(OutputInterface $output) {
    if ($cid = \CRM_Core_Session::getLoggedInContactID()) {
      return $cid;
    }

    // Ugh, this codepath is ridiculous.
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
        \CRM_Core_BAO_UFMatch::synchronize(\JFactory::getUser(), TRUE, CIVICRM_UF, 'Individual');
        break;

      case 'Standalone':
        // At time of writing, support for 'loggedInUser' is in a PR. Once the functionality is merged, we can update/simplify this.
        if (empty($GLOBALS['loggedInUser'])) {
          $this->bootLogger($output)->error('Failed to sync user/contact. You may be running a pre-release of civicrm-standalone.');

        }
        else {
          \CRM_Core_BAO_UFMatch::synchronize($GLOBALS['loggedInUser'], TRUE, CIVICRM_UF, 'Individual');
        }
        break;

      case 'WordPress':
        \CRM_Core_BAO_UFMatch::synchronize($GLOBALS['current_user'], TRUE, CIVICRM_UF, 'Individual');
        break;

      default:
        $this->bootLogger($output)->error("Unrecognized UF: " . CIVICRM_UF);

    }

    return \CRM_Core_Session::getLoggedInContactID();
  }

  protected function setupErrorHandling(OutputInterface $output) {
    $this->bootLogger($output)->debug('Attempting to set verbose error reporting');

    // standard php debug chat settings
    error_reporting(E_ALL | (version_compare(phpversion(), '8.4', '<') ? E_STRICT : 0));
    // https://wiki.php.net/rfc/deprecations_php_8_4#remove_e_strict_error_level_and_deprecate_e_strict_constant
    // In theory, once cv shifts to 8.x only, we can simplify this.
    ini_set('display_errors', 'stderr');
    ini_set('display_startup_errors', TRUE);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return array
   */
  protected function createBootParams(InputInterface $input, OutputInterface $output) {
    $boot_params = [];
    if ($output->isDebug()) {
      $boot_params['output'] = $output;
    }
    if ($input->getOption('url')) {
      $boot_params['url'] = $input->getOption('url');
    }
    return $boot_params;
  }

  private function bootLogger(OutputInterface $output): InternalLogger {
    return new SymfonyConsoleLogger('BootTrait', $output);
  }

  /**
   * @return bool
   */
  protected function isBooted() {
    return defined('CIVICRM_DSN');
  }

  protected function assertBooted() {
    if (!$this->isBooted()) {
      throw new \Exception("Error: This command requires bootstrapping, but the system does not appear to be bootstrapped. Perhaps you set --level=none?");
    }
  }

  /**
   * @return array{auto: bool, default: string, allow: string[]}
   */
  public function getBootOptions(): array {
    return $this->bootOptions;
  }

  /**
   * @param array{auto: bool, default: string, allow: string[]} $bootOptions
   * @return $this
   */
  public function setBootOptions(array $bootOptions) {
    $this->bootOptions = array_merge($this->bootOptions, $bootOptions);
    return $this;
  }

}
