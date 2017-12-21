<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Config;
use Civi\Cv\Encoder;
use Civi\Cv\SiteConfigReader;
use Civi\Cv\Util\CliEditor;
use Civi\Cv\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;


class FillCommand extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;

  protected $fields;

  protected function configure() {
    $this
      ->setName('vars:fill')
      ->setDescription('Generate a configuration file for any missing site data')
      ->addOption('file', NULL, InputOption::VALUE_REQUIRED, 'Read existing configuration from a file');
    $this->configureBootOptions();
  }

  public function __construct($name = NULL) {
    parent::__construct($name);
    $this->defaults = array(
      'ADMIN_EMAIL' => 'admin@example.com',
      'ADMIN_PASS' => 't0ps3cr3t',
      'ADMIN_USER' => 'admin',
      'CIVI_CORE' => '',
      'CIVI_DB_DSN' => 'mysql://dbUser:dbPass@dbHost/dbName?new_link=true',
      'CIVI_FILES' => '',
      'CIVI_SETTINGS' => '',
      'CIVI_SITE_KEY' => '',
      'CIVI_TEMPLATEC' => '',
      'CIVI_UF' => '',
      'CIVI_URL' => '',
      'CIVI_VERSION' => '',
      'CMS_DB_DSN' => 'mysql://dbUser:dbPass@dbHost/dbName?new_link=true',
      'CMS_ROOT' => '',
      'CMS_TITLE' => 'Untitled installation',
      'CMS_URL' => '',
      'CMS_VERSION' => '',
      'DEMO_EMAIL' => 'demo@example.com',
      'DEMO_PASS' => 't0ps3cr3t',
      'DEMO_USER' => 'demo',
      'IS_INSTALLED' => '1',
      'SITE_TOKEN' => md5(openssl_random_pseudo_bytes(256)),
      'SITE_TYPE' => '',
      'TEST_DB_DSN' => 'mysql://dbUser:dbPass@dbHost/dbName?new_link=true',
    );
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if (!$input->getOption('file')) {
      $this->boot($input, $output);
      $reader = new SiteConfigReader(CIVICRM_SETTINGS_PATH);
      $liveData = $reader->compile(array('buildkit', 'home', 'active'));
    }
    else {
      $liveData = json_decode(file_get_contents($input->getOption('file')), 1);
    }

    if ($liveData === NULL) {
      throw new \RuntimeException("Failed to extract current configuration.");
    }

    $siteConfig = array();
    foreach ($this->defaults as $field => $value) {
      if (!isset($liveData[$field])) {
        $siteConfig[$field] = $value;
      }
    }

    $output->writeln(sprintf("<info>Site:</info> %s", CIVICRM_SETTINGS_PATH));
    if (empty($siteConfig)) {
      $output->writeln("<info>No extra fields are required.</info>");
    }
    else {
      $output->writeln(sprintf("<info>These fields were missing. Setting defaults:</info>"));
      $output->writeln(Encoder::encode($siteConfig, 'json-pretty'));
      Config::update(function ($config) use ($siteConfig, $output) {
        if (isset($config['sites'][CIVICRM_SETTINGS_PATH])) {
          $config['sites'][CIVICRM_SETTINGS_PATH] = array_merge($siteConfig, $config['sites'][CIVICRM_SETTINGS_PATH]);
        }
        else {
          $config['sites'][CIVICRM_SETTINGS_PATH] = $siteConfig;
        }
        ksort($config['sites'][CIVICRM_SETTINGS_PATH]);
        return $config;
      });
      $output->writeln(sprintf("<info>Please edit</info> %s", Config::getFileName()));
    }
  }

}
