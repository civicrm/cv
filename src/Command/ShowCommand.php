<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\BuildkitReader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ShowCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('show')
      ->setDescription('Show the configuration of the local CiviCRM installation')
      ->addOption('buildkit', NULL, InputOption::VALUE_NONE, 'Find and return buildkit config')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (json,none,php,pretty,shell)', Application::getDefaultOut());
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

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
    $this->sendResult($input, $output, $data);
  }

}
