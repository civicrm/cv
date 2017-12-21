<?php
namespace Civi\Cv\Util;

use Civi\Cv\Encoder;
use Civi\Cv\Json;
use Civi\Cv\SiteConfigReader;
use Civi\Cv\Util\ArrayUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This trait can be mixed into a Symfony `Command` to provide Civi/CMS-bootstrap options.
 *
 * It first boots Civi and then boots the CMS.
 */
trait BootTrait {

  public function configureBootOptions() {
    $this->addOption('level', NULL, InputOption::VALUE_REQUIRED, 'Bootstrap level (none,classloader,settings,full)', 'full');
    $this->addOption('test', 't', InputOption::VALUE_NONE, 'Bootstrap the test database (CIVICRM_UF=UnitTests)');
    $this->addOption('user', 'U', InputOption::VALUE_REQUIRED, 'CMS user');
  }

  public function boot(InputInterface $input, OutputInterface $output) {
    if ($output->isDebug()) {
      $output->writeln(
        'Attempting to set verbose error reporting',
        OutputInterface::VERBOSITY_DEBUG);
      // standard php debug chat settings
      error_reporting(E_ALL | E_STRICT);
      ini_set('display_errors', TRUE);
      ini_set('display_startup_errors', TRUE);
      // add the output object to allow the bootstrapper to output debug messages
      // and track verboisty
      $boot_params = array(
        'output' => $output,
      );
    }
    else {
      $boot_params = array();
    }

    $output->writeln('<info>[BaseCommand::boot]</info> Start', OutputInterface::VERBOSITY_DEBUG);

    if ($input->hasOption('test') && $input->getOption('test')) {
      $output->writeln('<info>[BaseCommand::boot]</info> Use test mode', OutputInterface::VERBOSITY_DEBUG);
      putenv('CIVICRM_UF=UnitTests');
      $_ENV['CIVICRM_UF'] = 'UnitTests';
    }

    if ($input->hasOption('level') && $input->getOption('level') === 'none') {
      $output->writeln('<info>[BaseCommand::boot]</info> Skip', OutputInterface::VERBOSITY_DEBUG);
      return;
    }
    elseif ($input->hasOption('level') && $input->getOption('level') !== 'full') {
      $output->writeln('<info>[BaseCommand::boot]</info> Call basic cv bootstrap (' . $input->getOption('level') . ')', OutputInterface::VERBOSITY_DEBUG);
      \Civi\Cv\Bootstrap::singleton()->boot($boot_params + array(
          'prefetch' => FALSE,
        ));
    }
    else {
      $output->writeln('<info>[BaseCommand::boot]</info> Call standard cv bootstrap', OutputInterface::VERBOSITY_DEBUG);
      \Civi\Cv\Bootstrap::singleton()->boot($boot_params);

      $output->writeln('<info>[BaseCommand::boot]</info> Call core bootstrap', OutputInterface::VERBOSITY_DEBUG);
      \CRM_Core_Config::singleton();

      $output->writeln('<info>[BaseCommand::boot]</info> Call CMS bootstrap', OutputInterface::VERBOSITY_DEBUG);
      \CRM_Utils_System::loadBootStrap(array(), FALSE);

      if ($input->getOption('user')) {
        $output->writeln('<info>[BaseCommand::boot]</info> Set system user', OutputInterface::VERBOSITY_DEBUG);
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
    }

    $output->writeln('<info>[BaseCommand::boot]</info> Finished', OutputInterface::VERBOSITY_DEBUG);
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

}
