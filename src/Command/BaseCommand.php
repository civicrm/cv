<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\Util\OptionCallbackTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseCommand extends Command {

  use OptionCallbackTrait;

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->runOptionCallbacks($input, $output);
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
    $this->assertBooted();
    $params['debug'] = 1;
    if (!isset($params['version'])) {
      $params['version'] = 3;
    }
    $output->writeln("Calling $entity $action API", OutputInterface::VERBOSITY_DEBUG);
    $result = \civicrm_api($entity, $action, $params);
    if (!empty($result['is_error']) || $output->isDebug()) {
      $data = array(
        'entity' => $entity,
        'action' => $action,
        'params' => $params,
        'result' => $result,
      );
      if (!empty($result['is_error'])) {
        $output->getErrorOutput()->writeln("<error>Error: API Call Failed</error>: "
          . Encoder::encode($data, 'pretty'));
      }
      else {
        $output->writeln("API success" . Encoder::encode($data, 'pretty'),
          OutputInterface::VERBOSITY_DEBUG);
      }
    }
    return $result;
  }

  /**
   * Parse an option's data. This is for options where the default behavior
   * (of total omission) differs from the activated behavior
   * (of an active but unspecified option).
   *
   * Example, suppose we want these interpretations:
   *   cv en         ==> Means "--refresh=auto"; see $omittedDefault
   *   cv en -r      ==> Means "--refresh=yes"; see $activeDefault
   *   cv en -r=yes  ==> Means "--refresh=yes"
   *   cv en -r=no   ==> Means "--refresh=no"
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param array $rawNames
   *   Ex: array('-r', '--refresh').
   * @param string $omittedDefault
   *   Value to use if option is completely omitted.
   * @param string $activeDefault
   *   Value to use if option is activated without data.
   * @return string
   */
  public function parseOptionalOption(InputInterface $input, $rawNames, $omittedDefault, $activeDefault) {
    $value = NULL;
    foreach ($rawNames as $rawName) {
      if ($input->hasParameterOption($rawName)) {
        if (NULL === $input->getParameterOption($rawName)) {
          return $activeDefault;
        }
        else {
          return $input->getParameterOption($rawName);
        }
      }
    }
    return $omittedDefault;
  }

}
