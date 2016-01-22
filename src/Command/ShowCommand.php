<?php
namespace Civi\Cv\Command;

use Civi\Cv\BuildkitReader;
use Civi\Cv\Config;
use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ShowCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('vars:show')
      ->setDescription('Show the configuration of the local CiviCRM installation')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat());
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    $data = array();
    if ($shFile = BuildkitReader::findShFile(CIVICRM_SETTINGS_PATH)) {
      $data = array_merge($data, BuildkitReader::readShFile($shFile));
    }
    $data = array_merge($data, $this->readActiveSite());
    $config = Config::read();
    if (isset($config['sites'][CIVICRM_SETTINGS_PATH])) {
      $data = array_merge($data, $config['sites'][CIVICRM_SETTINGS_PATH]);
    }

    $this->cleanPaths($data);
    $this->cleanUrls($data);
    $this->cleanDbs($data);
    ksort($data);

    $this->sendResult($input, $output, $data);
  }

  /**
   * @return array
   */
  protected function readActiveSite() {
    $paths = is_callable(array('Civi', 'paths')) ? \Civi::paths() : NULL;
    $data = array(
      'CMS_DB_DSN' => CIVICRM_UF_DSN,
      'CMS_VERSION' => \CRM_Core_Config::singleton()->userSystem->getVersion(),
      'CIVI_DB_DSN' => CIVICRM_DSN,
      'CIVI_SITE_KEY' => CIVICRM_SITE_KEY,
      'CIVI_VERSION' => \CRM_Utils_System::version(),
      'CIVI_SETTINGS' => CIVICRM_SETTINGS_PATH,
      'CIVI_TEMPLATEC' => \CRM_Core_Config::singleton()->templateCompileDir,
      'CIVI_UF' => \CRM_Core_Config::singleton()->userFramework,
      'IS_INSTALLED' => '1',
      'SITE_TYPE' => 'cv-auto',
      'CMS_URL' => $paths
        ? \Civi::paths()->getUrl('[cms.root]/', 'absolute')
        : \CRM_Utils_System::languageNegotiationURL(\CRM_Utils_System::baseCMSURL(), FALSE, TRUE),
      'CMS_ROOT' => $paths
        ? \Civi::paths()->getPath('[cms.root]/.')
        : \CRM_Core_Config::singleton()->userSystem->cmsRootPath(),
      'CIVI_CORE' => $paths
        ? \Civi::paths()->getPath('[civicrm.root]/.')
        : $GLOBALS['civicrm_root'],
      'CIVI_URL' => $paths // NOTE: not in bulidkit!
        ? \Civi::paths()->getUrl('[civicrm.root]/', 'absolute')
        : '',
      'CIVI_FILES' => $paths
        ? \Civi::paths()->getPath('[civicrm.root]/.')
        : dirname(\CRM_Core_Config::singleton()->templateCompileDir), // ugh
    );

    return $data;
  }

  protected function parseDsn($prefix, $dsn) {
    $url = parse_url($dsn);

    // Ex: CMS_DB_ARGS="-h 127.0.0.1 -u username -ptopsecret -P 3307 dbname"
    $parts = array();
    if (!empty($url['host'])) {
      $parts[] = '-h';
      $parts[] = $url['host'];
    }
    if (!empty($url['user'])) {
      $parts[] = '-u';
      $parts[] = $url['user'];
    }
    if (!empty($url['pass'])) {
      $parts[] = '-p' . $url['user'];
    }
    if (!empty($url['port'])) {
      $parts[] = '-P';
      $parts[] = $url['port'];
    }
    if (!empty($url['path'])) {
      $parts[] = trim($url['path'], '/');
    }
    foreach ($parts as $part) {
      if (!preg_match('/^[a-zA-Z0-9\._+\-]*$/', $part)) {
        throw new \InvalidArgumentException("Cannot format DB CLI arguments");
      }
    }

    return array(
      "{$prefix}_DB_USER" => empty($url['user']) ? '' : $url['user'],
      "{$prefix}_DB_PASS" => empty($url['pass']) ? '' : $url['pass'],
      "{$prefix}_DB_HOST" => empty($url['host']) ? '' : $url['host'],
      "{$prefix}_DB_PORT" => empty($url['port']) ? '' : $url['port'],
      "{$prefix}_DB_NAME" => empty($url['path']) ? '' : trim($url['path'], '/'),
      "{$prefix}_DB_ARGS" => implode(' ', $parts),
    );
  }

  /**
   * @param $data
   */
  protected function cleanPaths(&$data) {
    $paths = array('CIVI_CORE', 'CIVI_FILES', 'CIVI_TEMPLATEC', 'CMS_ROOT');
    foreach ($paths as $path) {
      $data[$path] = rtrim($data[$path], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
  }

  /**
   * @param $data
   */
  protected function cleanUrls(&$data) {
    $urls = array('CMS_URL', 'CIVI_URL');
    foreach ($urls as $url) {
      $data[$url] = rtrim($data[$url], '/') . '/';
    }
  }

  /**
   * @param $data
   */
  protected function cleanDbs(&$data) {
    foreach (array('CIVI', 'CMS', 'TEST') as $prefix) {
      if (isset($data["{$prefix}_DB_DSN"])) {
        $data = array_merge($data, $this->parseDsn($prefix, $data["{$prefix}_DB_DSN"]));
      }
    }
  }

}
