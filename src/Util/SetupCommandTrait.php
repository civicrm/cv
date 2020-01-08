<?php
namespace Civi\Cv\Util;

use Civi\Setup\DbUtil;
use Civi\Cv\Util\BootTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

define('CV_SETUP_PROTOCOL_VER', '1.0');

/**
 * This trait can be mixed into a Symfony `Command` to take advantage of the
 * civicrm-setup framework.
 */
trait SetupCommandTrait {
  use BootTrait;

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
      ->addOption('plugin-path', NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'A directory with extra installer plugins')
      ->addOption('cms-base-url', NULL, InputOption::VALUE_OPTIONAL, 'The URL of the CMS (If omitted, attempt to autodetect.)')
      ->addOption('lang', NULL, InputOption::VALUE_OPTIONAL, 'Specify the installation language')
      ->addOption('comp', NULL, InputOption::VALUE_OPTIONAL, 'Comma-separated list of CiviCRM components to enable. (Ex: CiviEvent,CiviContribute,CiviMember,CiviMail,CiviReport)')
      ->addOption('ext', NULL, InputOption::VALUE_OPTIONAL, 'Comma-separated list of CiviCRM extensions to enable. (Ex: org.civicrm.shoreditch,org.civicrm.flexmailer)')
      ->addOption('db', NULL, InputOption::VALUE_OPTIONAL, 'Database credentials for primary Civi database.')
      ->addOption('model', 'm', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Set additional field in the model. Key-value pair.');

    return $this;
  }

  /**
   * Initialize the Civi\Setup environment.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param int $defaultOutputOptions
   *   Extra options for displaying bootstrap messages.
   *   Ex: OutputInterface::VERBOSITY_NORMAL
   * @return \Civi\Setup
   * @throws \Exception
   */
  protected function bootSetupSubsystem(InputInterface $input, OutputInterface $output, $defaultOutputOptions = 0) {
    $this->setupHost($input);
    $b = $this->_boot_cms_only($input, $output);

    // Initialize setup model.
    $setupOptions = array();
    $setupOptions['cms'] = $b->getBootedCmsType();

    $possibleSrcPaths = [
      $input->getOption('src-path'),
      getenv('CV_SETUP_SRC_PATH'),
      implode(DIRECTORY_SEPARATOR, [$b->getBootedCmsPath(), 'sites', 'all', 'modules', 'civicrm']),
      implode(DIRECTORY_SEPARATOR, [$b->getBootedCmsPath(), 'wp-content', 'plugins', 'civicrm', 'civicrm']),
      implode(DIRECTORY_SEPARATOR, [$b->getBootedCmsPath(), 'modules', 'civicrm']),
      implode(DIRECTORY_SEPARATOR, [$b->getBootedCmsPath(), 'vendor', 'civicrm', 'civicrm-core']),
      implode(DIRECTORY_SEPARATOR, [dirname($b->getBootedCmsPath()), 'vendor', 'civicrm', 'civicrm-core']),
    ];
    $setupOptions['srcPath'] = ArrayUtil::pickFirst($possibleSrcPaths, 'file_exists');
    if ($setupOptions['srcPath']) {
      $output->writeln(sprintf('<info>Found code for <comment>%s</comment> in <comment>%s</comment></info>', 'civicrm-core', $setupOptions['srcPath']), $defaultOutputOptions);
    }
    else {
      $this->printPathError($output, 'civicrm-core', '--src-path', 'CV_SETUP_SRC_PATH', $possibleSrcPaths);
      throw new \Exception("Failed to locate civicrm-core");
    }

    $possibleSetupPaths = [
      $input->getOption('setup-path'),
      getenv('CV_SETUP_PATH'),
      implode(DIRECTORY_SEPARATOR, [$setupOptions['srcPath'], 'vendor', 'civicrm', 'civicrm-setup']),
      implode(DIRECTORY_SEPARATOR, [$setupOptions['srcPath'], 'packages', 'civicrm-setup']),
      implode(DIRECTORY_SEPARATOR, [$setupOptions['srcPath'], 'setup']),
      implode(DIRECTORY_SEPARATOR, [dirname($setupOptions['srcPath']), 'civicrm-setup']),
      implode(DIRECTORY_SEPARATOR, ['/usr', 'local', 'share', 'civicrm-setup']),
    ];
    $setupOptions['setupPath'] = ArrayUtil::pickFirst($possibleSetupPaths, 'file_exists');
    if ($setupOptions['setupPath']) {
      $output->writeln(sprintf('<info>Found code for <comment>%s</comment> in <comment>%s</comment></info>', 'civicrm-setup', $setupOptions['setupPath']), $defaultOutputOptions);
    }
    else {
      $this->printPathError($output, 'civicrm-setup', '--setup-path', 'CV_SETUP_PATH', $possibleSetupPaths);
      throw new \Exception("Failed to locate civicrm-setup");
    }

    // Note: We set 'cms-base-url' both before and after init. The "before"
    // lets us give hints to init code which reads cmsBaseUrl. The "after"
    // lets us override any changes made by init code (i.e. this user-input
    // is mandatory).
    if ($input->getOption('cms-base-url')) {
      $setupOptions['cmsBaseUrl'] = $input->getOption('cms-base-url');
    }

    $pluginCallback = function($pluginFiles) use ($input) {
      foreach ($input->getOption('plugin-path') as $pluginDir) {
        foreach (['*.civi-setup.php'] as $pattern) {
          foreach ((array) glob("$pluginDir/$pattern") as $file) {
            $key = substr($file, strlen($pluginDir) + 1);
            $key = preg_replace('/\.civi-setup\.php$/', '', $key);
            $pluginFiles[$key] = $file;
          }
        }
      }
      ksort($pluginFiles);
      return $pluginFiles;
    };

    $this->setupAutoloaders($setupOptions['srcPath'], $setupOptions['setupPath']);
    $c = new \ReflectionClass('Civi\Setup');
    if (substr($c->getFileName(), 0, strlen($setupOptions['setupPath'])) !== $setupOptions['setupPath']) {
      $effSetupPath = dirname(dirname($c->getFileName()));
      $output->writeln(sprintf('Warning: Autoloader prioritized code from <comment>%s</comment> instead of requested <comment>%s</comment>.', $effSetupPath, $setupOptions['setupPath']));
    }

    \Civi\Setup::assertProtocolCompatibility(CV_SETUP_PROTOCOL_VER);
    \Civi\Setup::init($setupOptions, $pluginCallback, new ConsoleLogger($output));
    $setup = \Civi\Setup::instance();

    // Override defaults detected by setup initialization.
    $setup->getModel()->settingsPath = ArrayUtil::pickFirst([
      $input->getOption('settings-path'),
      getenv('CV_SETUP_SETTINGS'),
      $setup->getModel()->settingsPath,
    ]);
    $setup->getModel()->cmsBaseUrl = ArrayUtil::pickFirst([
      $input->getOption('cms-base-url'),
      $setup->getModel()->cmsBaseUrl,
    ]);
    if ($input->getOption('db')) {
      $setup->getModel()->db = DbUtil::parseDsn($input->getOption('db'));
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
    foreach ($input->getOption('model') as $modelExpr) {
      $obj = $setup->getModel();
      list ($key, $value) = explode('=', $modelExpr, 2);
      $keyPath = explode('.', $key);
      $firstKey = array_shift($keyPath);
      if ($keyPath) {
        ArrayUtil::pathSet($obj->{$firstKey}, $keyPath, $value);
      }
      else {
        $obj->{$firstKey} = $value;
      }
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
    // Optional - Use our own setup autoloader.
    $setupAL = implode(DIRECTORY_SEPARATOR, [$setupPath, 'civicrm-setup-autoload.php']);
    if (file_exists($setupAL)) {
      require_once $setupAL;
    }

    // Required - Use core's autoloader.
    $coreAL = implode(DIRECTORY_SEPARATOR, [$srcPath, 'CRM', 'Core', 'ClassLoader.php']);
    if (!file_exists($coreAL)) {
      throw new \Exception("Failed to load $coreAL");
    }
    require_once $coreAL;

    \CRM_Core_ClassLoader::singleton()->register();
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $name
   * @param $optName
   * @param $envName
   * @param $possibleSrcPaths
   */
  protected function printPathError(OutputInterface $output, $name, $optName, $envName, $possibleSrcPaths) {
    $output->writeln(sprintf('<error>Failed to locate %s</error>', $name));
    $output->writeln(sprintf('<info>Consider setting <comment>%s</comment>, setting <comment>%s</comment>, or placing it one of these folders:</info>', $optName, $envName));
    foreach ($possibleSrcPaths as $path) {
      if ($path) {
        $output->writeln(sprintf('<info> * <comment>%s</comment></info>', $path));
      }
    }
  }

}
