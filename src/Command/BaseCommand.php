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
    if ($input->hasOption('test') && $input->getOption('test')) {
      putenv('CIVICRM_UF=UnitTests');
      $_ENV['CIVICRM_UF'] = 'UnitTests';
    }

    if ($input->hasOption('level') && $input->getOption('level') !== 'full') {
      \Civi\Cv\Bootstrap::singleton()->boot(array(
        'prefetch' => FALSE,
      ));
    }
    else {
      \Civi\Cv\Bootstrap::singleton()->boot();
      \CRM_Core_Config::singleton();
      \CRM_Utils_System::loadBootStrap(array(), FALSE);
      if ($input->getOption('user')) {
        if (is_callable(array(\CRM_Core_Config::singleton()->userSystem, 'loadUser'))) {
          \CRM_Utils_System::loadUser($input->getOption('user'));
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
    $result = \civicrm_api($entity, $action, $params);
    if (!empty($result['is_error'])) {
      $data = array(
        'entity' => $entity,
        'action' => $action,
        'params' => $params,
        'result' => $result,
      );
      $output->writeln("<error>Error: API Call Failed</error>: " . Encoder::encode($data, 'pretty'));
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
