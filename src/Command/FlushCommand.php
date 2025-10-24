<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\ArrayUtil;
use Civi\Cv\Util\VerboseApi;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FlushCommand extends CvCommand {

  protected function configure() {
    $this
      ->setName('flush')
      ->setAliases(array())
      ->addOption('triggers', 'T', InputOption::VALUE_NONE, 'Rebuild triggers')
      ->addOption('exclude', 'E', InputOption::VALUE_OPTIONAL, 'Exclude specific components (comma-separated), e.g. --exclude=entities,userjobs (only available in CiviCRM 6.1 or newer)')
      ->setDescription('Flush system caches')
      ->setHelp('
Flush system caches
');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    // The main reason we have this as separate command -- so we can ignore
    // stale class-references that might be retained by the container cache.
    define('CIVICRM_CONTAINER_CACHE', 'never');

    // Now we can let the parent proceed with bootstrap...
    parent::initialize($input, $output);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $params = array();
    if ($input->getOption('triggers')) {
      $params['triggers'] = TRUE;
    }

    $output->writeln("<info>Flushing system caches</info>");

    // the modern way (from core 6.1 on)
    if (is_callable(['Civi', 'rebuild'])) {
      $default_params = ['*' => TRUE, 'triggers' => FALSE, 'sessions' => FALSE];
      if ($input->getOption('triggers')) {
        $default_params['triggers'] = TRUE;
      }
      if ($input->getOption('exclude')) {
        $exclude_param = $input->getOption('exclude');
        $excludes = explode(',', $exclude_param);
        foreach ($excludes as $exclude) {
          $default_params[$exclude] = FALSE;
        }
      }
      if ($output->isVerbose()) {
        \Civi\Cv\Cv::io()->table(
          ['target', 'enabled'],
          ArrayUtil::mapKV($default_params, fn($k, $v) => [$k, $v ? 'yes' : 'no'])
        );
      }
      \Civi::rebuild($default_params)->execute();
      return 0;
    }

    // the old way
    $result = VerboseApi::callApi3Success('System', 'flush', $params);
    return empty($result['is_error']) ? 0 : 1;
  }

}
