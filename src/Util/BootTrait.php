<?php
namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This trait can be mixed into a Symfony `Command` to provide Civi/CMS-bootstrap options.
 *
 * It first boots Civi and then boots the CMS.
 */
trait BootTrait {

  public function configureBootOptions($defaultLevel = 'full') {
    $this->addOption('level', NULL, InputOption::VALUE_REQUIRED, 'Bootstrap level (none,classloader,settings,full,cms-only,cms-full)', $defaultLevel);
    $this->addOption('hostname', NULL, InputOption::VALUE_REQUIRED, 'Hostname (for a multisite system)');
    $this->addOption('test', 't', InputOption::VALUE_NONE, 'Bootstrap the test database (CIVICRM_UF=UnitTests)');
    $this->addOption('user', 'U', InputOption::VALUE_REQUIRED, 'CMS user');
  }

  public function boot(InputInterface $input, OutputInterface $output) {
    $output->writeln('<info>[BootTrait]</info> Start', OutputInterface::VERBOSITY_DEBUG);

    $this->setupErrorHandling($output);
    $this->setupHost($input);

    if ($input->hasOption('test') && $input->getOption('test')) {
      $output->writeln('<info>[BootTrait]</info> Use test mode', OutputInterface::VERBOSITY_DEBUG);
      putenv('CIVICRM_UF=UnitTests');
      $_ENV['CIVICRM_UF'] = 'UnitTests';
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

    $output->writeln('<info>[BootTrait]</info> Finished', OutputInterface::VERBOSITY_DEBUG);
  }

  /**
   * Do not do anything for bootstrap. Just use the given environment.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function _boot_none(InputInterface $input, OutputInterface $output) {
    $output->writeln('<info>[BootTrait]</info> Skip', OutputInterface::VERBOSITY_DEBUG);
  }

  /**
   * Setup the CiviCRM classloader.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  public function _boot_classloader(InputInterface $input, OutputInterface $output) {
    $output->writeln('<info>[BootTrait]</info> Call basic cv bootstrap (' . $input->getOption('level') . ')', OutputInterface::VERBOSITY_DEBUG);
    \Civi\Cv\Bootstrap::singleton()->boot($this->createBootParams($output) + array(
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
    $output->writeln('<info>[BootTrait]</info> Call basic cv bootstrap (' . $input->getOption('level') . ')', OutputInterface::VERBOSITY_DEBUG);
    \Civi\Cv\Bootstrap::singleton()->boot($this->createBootParams($output) + array(
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
    $output->writeln('<info>[BootTrait]</info> Call standard cv bootstrap', OutputInterface::VERBOSITY_DEBUG);
    \Civi\Cv\Bootstrap::singleton()->boot($this->createBootParams($output));

    $output->writeln('<info>[BootTrait]</info> Call core bootstrap', OutputInterface::VERBOSITY_DEBUG);
    \CRM_Core_Config::singleton();

    $output->writeln('<info>[BootTrait]</info> Call CMS bootstrap', OutputInterface::VERBOSITY_DEBUG);
    \CRM_Utils_System::loadBootStrap(array(), FALSE);

    if ($input->getOption('user')) {
      $output->writeln('<info>[BootTrait]</info> Set system user', OutputInterface::VERBOSITY_DEBUG);
      if (is_callable(array(\CRM_Core_Config::singleton()->userSystem, 'loadUser'))) {
        \CRM_Core_Config::singleton()->userSystem->loadUser($input->getOption('user'));
        if (!$this->ensureUserContact($output)) {
          throw new \Exception("Failed to determine contactID for user=" . $input->getOption('user'));
        }
      }
      else {
        $output->getErrorOutput()->writeln("<error>Failed to set user. Feature not supported by UF (" . CIVICRM_UF . ")</error>");
      }
    }

    if (is_callable([\CRM_Core_Config::singleton()->userSystem, 'setMySQLTimeZone'])) {
      $output->writeln('<info>[BootTrait]</info> Set active MySQL timezone', OutputInterface::VERBOSITY_DEBUG);
      \CRM_Core_Config::singleton()->userSystem->setMySQLTimeZone();
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
    $output->writeln('<info>[BootTrait]</info> Call CMS bootstrap', OutputInterface::VERBOSITY_DEBUG);
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
    $output->writeln('<info>[BootTrait]</info> Call Civi bootstrap', OutputInterface::VERBOSITY_DEBUG);
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
        \CRM_Core_BAO_UFMatch::synchronize($GLOBALS['user'], TRUE,
          CIVICRM_UF, 'Individual');
        break;

      case 'Drupal8':
        \CRM_Core_BAO_UFMatch::synchronize(\Drupal::currentUser(), TRUE,
          CIVICRM_UF, 'Individual');
        break;

      case 'Joomla':
        \CRM_Core_BAO_UFMatch::synchronize(\JFactory::getUser(), TRUE,
          CIVICRM_UF, 'Individual');
        break;

      case 'WordPress':
        \CRM_Core_BAO_UFMatch::synchronize($GLOBALS['current_user'], TRUE,
          CIVICRM_UF, 'Individual');
        break;

      default:
        $output->writeln("<error>Unrecognized UF: " . CIVICRM_UF . "</error>");
    }

    return \CRM_Core_Session::getLoggedInContactID();
  }

  protected function setupHost(InputInterface $input) {
    if ($input->hasOption('hostname')) {
      $_SERVER['HTTP_HOST'] = $input->getOption('hostname');
    }
  }

  protected function setupErrorHandling(OutputInterface $output) {
    $output->writeln('[BootTrait] Attempting to set verbose error reporting', OutputInterface::VERBOSITY_DEBUG);
    // standard php debug chat settings
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', TRUE);
    ini_set('display_startup_errors', TRUE);
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return array
   */
  protected function createBootParams(OutputInterface $output) {
    if ($output->isDebug()) {
      $boot_params = array('output' => $output);
    }
    else {
      $boot_params = array();
    }
    return $boot_params;
  }

}
