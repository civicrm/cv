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
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('find')
      ->setDescription('Find the configuration of the local CiviCRM installation')
      ->addOption('buildkit', NULL, InputOption::VALUE_NONE, 'Find and return buildkit config');
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

    \CRM_Core_Config::singleton();
    if (is_callable(array('Civi', 'paths'))) {
      // Civi v4.7+
      $data = array(
        'CIVICRM_SETTINGS_PATH' => CIVICRM_SETTINGS_PATH,
        'VERSION' => \CRM_Utils_System::version(),
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
    }
    else {
      // Civi v4.6 and earlier
      $data = array(
        'CIVICRM_SETTINGS_PATH' => CIVICRM_SETTINGS_PATH,
        'VERSION' => \CRM_Utils_System::version(),
        'civicrm' => array(
          'root' => array(
            'path' => $GLOBALS['civicrm_root'],
          ),
        ),
        'cms' => array(
          'root' => array(
            'url' => \CRM_Utils_System::languageNegotiationURL(\CRM_Utils_System::baseCMSURL(), FALSE, TRUE),
            'path' => \CRM_Core_Config::singleton()->userSystem->cmsRootPath(),
          ),
        ),
      );

    }
    if ($buildkitData) {
      $data['buildkit'] = $buildkitData;
    }
    $opt = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
    $output->write(json_encode($data, $opt));
  }

}
