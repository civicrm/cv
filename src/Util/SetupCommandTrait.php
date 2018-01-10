<?php
namespace Civi\Cv\Util;

use Civi\Setup\DbUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This trait can be mixed into a Symfony `Command` to take advantage of the
 * civicrm-setup framework.
 */
trait SetupCommandTrait {
  use \Civi\Cv\Util\CmsBootTrait;

  /**
   * Register any CLI options which affect the initialization of the
   * Civi\Setup runtime.
   *
   * @return $this
   */
  public function configureSetupOptions() {
    $this
      ->addOption('settings-path', NULL, InputOption::VALUE_OPTIONAL, 'The path to CivCRM settings file. (If omitted, use CV_SETUP_SETTINGS or try to use default.)')
      ->addOption('setup-path', NULL, InputOption::VALUE_OPTIONAL, 'The path to CivCRM-Setup source tree. (If omitted, read CV_SETUP_PATH or scan common defaults.)')
      ->addOption('src-path', NULL, InputOption::VALUE_OPTIONAL, 'The path to CivCRM-Core source tree. (If omitted, read CV_SETUP_SRC_PATH or scan common defaults.)')
      ->addOption('cms-base-url', NULL, InputOption::VALUE_OPTIONAL, 'The URL of the CMS (If omitted, attempt to autodetect.)')
      ->addOption('lang', NULL, InputOption::VALUE_OPTIONAL, 'Specify the installation language')
      ->addOption('comp', NULL, InputOption::VALUE_OPTIONAL, 'Comma-separated list of CiviCRM components to enable. (Ex: CiviEvent,CiviContribute,CiviMember,CiviMail,CiviReport)')
      ->addOption('ext', NULL, InputOption::VALUE_OPTIONAL, 'Comma-separated list of CiviCRM extensions to enable. (Ex: org.civicrm.shoreditch,org.civicrm.flexmailer)')
      ->addOption('db', NULL, InputOption::VALUE_OPTIONAL, 'Database credentials for primary Civi database.');
    return $this;
  }

  /**
   * Initialize the Civi\Setup environment.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Civi\Setup
   * @throws \Exception
   */
  protected function bootSetupSubsystem(InputInterface $input, OutputInterface $output) {
    $b = $this->bootCms($input, $output);

    // Initialize setup model.
    $setupOptions = array();
    $setupOptions['cms'] = $b->getBootedCmsType();

    $setupOptions['srcPath'] = ArrayUtil::pickFirst([
      $input->getOption('src-path'),
      getenv('CV_SETUP_SRC_PATH'),
      $this->findCiviSrcPath($b->getBootedCmsType(), $b->getBootedCmsPath()),
    ]);
    $setupOptions['setupPath'] = ArrayUtil::pickFirst([
      $input->getOption('setup-path'),
      getenv('CV_SETUP_PATH'),
      implode(DIRECTORY_SEPARATOR, [$setupOptions['srcPath'], 'setup']),
    ]);

    if (!$setupOptions['srcPath'] || !file_exists($setupOptions['srcPath'])) {
      throw new \Exception("The 'srcPath' is not a valid directory ({$setupOptions['srcPath']}). Consider downloading it, setting --src-path, or setting CV_SETUP_SRC_PATH.");
    }
    if (!$setupOptions['setupPath'] || !file_exists($setupOptions['setupPath'])) {
      throw new \Exception("The 'setupPath' is not a valid directory ({$setupOptions['setupPath']}). Consider downloading it, setting --setup-path, or setting CV_SETUP_PATH.");
    }

    $this->setupAutoloaders($setupOptions['srcPath'], $setupOptions['setupPath']);
    \Civi\Setup::init($setupOptions, NULL, new ConsoleLogger($output));
    $setup = \Civi\Setup::instance();

    // Override defaults detected by setup initialization.
    $setup->getModel()->settingsPath = ArrayUtil::pickFirst([
      $input->getOption('settings-path'),
      getenv('CV_SETUP_SETTINGS'),
      $setup->getModel()->settingsPath,
    ]);
    $setup->getModel()->cmsBaseUrl = ArrayUtil::pickFirst([
      $input->getOption('cms-base-url'),
      $setup->getModel()->cmsBaseUrl
    ]);
    if ($input->getOption('db')) {
      $setup->getModel()->db = DbUtil::parseDsn($input->getOption('db'));
      return $setup;
    }
    if ($input->getOption('lang')) {
      $setup->getModel()->lang = $input->getOption('lang');
    }
    if ($input->getOption('comp')) {
      $setup->getModel()->components = explode(',', $input->getOption('comp'));
    }
    if ($input->getOption('ext')) {
      $setup->getModel()->extensions = array_unique(
        array_merge(
          $setup->getModel()->extensions,
          explode(',', $input->getOption('ext'))
        )
      );
    }

    return $setup;
  }

  /**
   * @param string $srcPath
   *   Path to civicrm-core code.
   * @param string|NULL $setupPath
   *   Path to civicrm-setup code.
   * @throws \Exception
   */
  protected function setupAutoloaders($srcPath, $setupPath) {
    $autoloaders = [];
    $autoloaders[] = implode(DIRECTORY_SEPARATOR,
      [$setupPath, 'civicrm-setup-autoload.php']);
    $autoloaders[] = implode(DIRECTORY_SEPARATOR,
      [$srcPath, 'CRM', 'Core', 'ClassLoader.php']);

    foreach ($autoloaders as $autoloader) {
      if (!file_exists($autoloader)) {
        throw new \Exception("Failed to load $autoloader");
      }
      require_once $autoloader;
    }
    \CRM_Core_ClassLoader::singleton()->register();
  }

  /**
   * Try to guess the location of the main "civicrm" source tree.
   *
   * @param string $cmsType
   *   Ex: 'Backdrop', 'Drupal', 'Drupal8', 'Joomla', 'WordPress'.
   * @param string $cmsPath
   *   Ex: '/var/www'.
   * @return null|string
   */
  protected function findCiviSrcPath($cmsType, $cmsPath) {
    $commonDirs = array(
      'sites/all/modules/civicrm',
      'wp-content/plugins/civicrm/civicrm',
      'modules/civicrm',
    );
    foreach ($commonDirs as $commonDir) {
      if (file_exists($cmsPath . DIRECTORY_SEPARATOR . $commonDir)) {
        return $cmsPath . DIRECTORY_SEPARATOR . $commonDir;
      }
    }

    return NULL;
  }

}
