<?php
namespace Civi\Cv\Command;

use Civi\Cv\BuildkitReader;
use Civi\Cv\Util\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class FindCommand extends BaseCommand {

  /**
   * @var Filesystem
   */
  var $fs;

  /**
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('find')
      ->setDescription('Find the configuration of the local CiviCRM installation')
      ->addOption('buildkit', NULL, InputOption::VALUE_NONE, 'Find and return buildkit config')
      ->addOption('json', NULL, InputOption::VALUE_NONE, 'Enable JSON output format')
      ->addOption('php', NULL, InputOption::VALUE_NONE, 'Enable PHP output format');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
//    $input->setArgument('path', $this->fs->toAbsolutePaths($input->getArgument('path')));
//    $this->fs->validateExists($input->getArgument('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    \Civi\Cv\Bootstrap::singleton()->boot();

    $buildkitData = NULL;
    if ($input->getOption('buildkit')) {
      $shFile = BuildkitReader::findShFile(CIVICRM_SETTINGS_PATH);
      if ($shFile) {
        $buildkitData = BuildkitReader::readShFile($shFile);
        if ($buildkitData['CIVI_SETTINGS'] !== CIVICRM_SETTINGS_PATH) {
          throw new \RuntimeException(sprintf(
            "Found buildkit data (%s) which does not match active settings (%s)",
            $shFile,
            CIVICRM_SETTINGS_PATH
          ));
        }
      }
    }

    if ($input->getOption('json')) {
      \CRM_Core_Config::singleton();
      $data = array(
        'CIVICRM_SETTINGS_PATH' => CIVICRM_SETTINGS_PATH,
        'civicrm' => array(
          'root' => array(
            'url' => \Civi::paths()->getUrl('[civicrm.root]/', 'absolute'),
            'path' => \Civi::paths()->getPath('[civicrm.root]/.'),
          ),
          'files' => array(
            'url' => \Civi::paths()->getUrl('[civicrm.files]/', 'absolute'),
            'path' => \Civi::paths()->getPath('[civicrm.root]/.'),
          ),
        ),
        'cms' => array(
          'root' => array(
            'url' => \Civi::paths()->getUrl('[cms.root]/', 'absolute'),
            'path' => \Civi::paths()->getPath('[cms.root]/.'),
          ),
        ),
      );
      if ($buildkitData) {
        $data['buildkit'] = $buildkitData;
      }
      $opt = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
      $output->write(json_encode($data, $opt));
    }
    elseif ($input->getOption('php')) {
      $output->writeln(sprintf('$_SERVER[\'SCRIPT_FILENAME\'] = %s;', var_export($_SERVER['SCRIPT_FILENAME'], TRUE)));
      $output->writeln(sprintf('define(\'CIVICRM_SETTINGS_PATH\', %s);', var_export(CIVICRM_SETTINGS_PATH, TRUE)));
      $output->writeln(sprintf('include_once CIVICRM_SETTINGS_PATH;'));
      $output->writeln(sprintf('global $civicrm_root;'));
      $output->writeln(sprintf('require_once $civicrm_root . "/CRM/Core/ClassLoader.php";'));
      $output->writeln(sprintf('\CRM_Core_ClassLoader::singleton()->register();'));
      if ($buildkitData) {
        $output->writeln(sprintf('global $civibuild; $civibuild = %s;', var_export($buildkitData, TRUE)));
      }
    }
    else {
      $output->writeln('<error>Must specify --json or --php</error>');
      return 1;
    }
  }
}
