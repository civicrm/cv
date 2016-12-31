<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\Json;
use Civi\Cv\SiteConfigReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BaseCommand extends Command {

  protected function configureBootOptions() {
    $this->addOption('level', NULL, InputOption::VALUE_REQUIRED, 'Bootstrap level (classloader,settings,full)', 'full');
    $this->addOption('test', 't', InputOption::VALUE_NONE, 'Bootstrap the test database (CIVICRM_UF=UnitTests)');
    $this->addOption('user', 'U', InputOption::VALUE_REQUIRED, 'CMS user');
  }

  protected function boot(InputInterface $input, OutputInterface $output) {
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
        'output' => $output
      );
    }
    else {
      $boot_params = array();
    }

    $output->writeln('Booting', OutputInterface::VERBOSITY_DEBUG);
    if ($input->hasOption('test') && $input->getOption('test')) {
      putenv('CIVICRM_UF=UnitTests');
      $_ENV['CIVICRM_UF'] = 'UnitTests';
    }

    if ($input->hasOption('level') && $input->getOption('level') !== 'full') {
      $output->writeln('Not prefetching', OutputInterface::VERBOSITY_DEBUG);
      \Civi\Cv\Bootstrap::singleton()->boot($boot_params + array(
        'prefetch' => FALSE,
      ));
    }
    else {
      $output->writeln('Doing full bootstrap', OutputInterface::VERBOSITY_DEBUG);
      \Civi\Cv\Bootstrap::singleton()->boot($boot_params);
      $output->writeln('Finished boot', OutputInterface::VERBOSITY_DEBUG);
      \CRM_Core_Config::singleton();
      $output->writeln('Finished config', OutputInterface::VERBOSITY_DEBUG);
      \CRM_Utils_System::loadBootStrap(array(), FALSE);
      $output->writeln('Finished load', OutputInterface::VERBOSITY_DEBUG);
      if ($input->getOption('user')) {
        if (is_callable(array(\CRM_Core_Config::singleton()->userSystem, 'loadUser'))) {
          \CRM_Utils_System::loadUser($input->getOption('user'));
          $output->writeln('Finished user load', OutputInterface::VERBOSITY_DEBUG);
        }
        else {
          $output->writeln("<error>Failed to set user. Feature not supported by UF (" . CIVICRM_UF . ")</error>");
        }
      }
    }
  }

  /**
   * Execute an API call. If it fails, display a formatted error.
   *
   * Note: If there is an error, we still return it softly so that the
   * command can exit gracefully.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $entity
   * @param $action
   * @param $params
   * @return mixed
   */
  protected function callApiSuccess(InputInterface $input, OutputInterface $output, $entity, $action, $params) {
    $params['debug'] = 1;
    if (!isset($params['version'])) {
      $params['version'] = 3;
    }
    $this->writeln("Calling $entity $action API", OutputInterface::VERBOSITY_DEBUG);
    $result = \civicrm_api($entity, $action, $params);
    if (!empty($result['is_error']) || $output->isDebug()) {
      $data = array(
        'entity' => $entity,
        'action' => $action,
        'params' => $params,
        'result' => $result,
      );
      if (!empty($result['is_error'])) {
        $output->writeln("<error>Error: API Call Failed</error>: "
          . Encoder::encode($data, 'pretty'));
      }
      else {
        $this->writeln("API success" . Encoder::encode($data, 'pretty'),
          OutputInterface::VERBOSITY_DEBUG);
      }
    }
    return $result;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $result
   */
  protected function sendResult(InputInterface $input, OutputInterface $output, $result) {
    $output->writeln(Encoder::encode($result, $input->getOption('out')));
  }

}
