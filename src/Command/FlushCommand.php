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
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Flush all (v6.1+)')
      ->addOption('include', 'I', InputOption::VALUE_OPTIONAL, 'Only flush specific targets (comma-separated) (v6.1+), e.g. --exclude=entities,userjobs (only available in CiviCRM 6.1 or newer)')
      ->addOption('triggers', 'T', InputOption::VALUE_NONE, 'Rebuild triggers')
      ->addOption('exclude', 'E', InputOption::VALUE_OPTIONAL, 'Exclude specific targets (comma-separated) (v6.1+), e.g. --exclude=entities,userjobs (only available in CiviCRM 6.1 or newer)')
      ->setDescription('Flush system caches')
      ->setHelp('
Flush system caches

Example: Flush most subsystems
$ cv flush

Example: Flush all subsystems (regardless of how slow/disruptive it might be) [v6.1+]
$ cv flush --all

Example: Flush ONLY the router and nav-menu [v6.9+]
$ cv flush --include router,navigation

Example: Flush everything EXCEPT managed-entities and form-states [v6.1+]
$ cv flush --all --exclude entities,sessions

If you need to precisely target with --include or --exclude, then you
should lookup available targets by consulting documentation for Civi::rebuild()
in your CiviCRM version.
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
    $output->writeln("<info>Flushing system caches</info>");

    // the modern way (from core 6.1 on)
    if (is_callable(['Civi', 'rebuild'])) {
      if ($input->getOption('all')) {
        $default_params = ['*' => TRUE];
      }
      elseif ($input->getOption('include')) {
        $default_params = ['*' => FALSE];
      }
      else {
        $default_params = ['*' => TRUE, 'triggers' => FALSE, 'sessions' => FALSE];
      }

      // Interpret other CLI args to fine-tune $default_params.
      if ($input->getOption('triggers')) {
        $default_params['triggers'] = TRUE;
      }
      if ($input->getOption('include')) {
        $includes = explode(',', $input->getOption('include'));
        foreach ($includes as $include) {
          $default_params[$include] = TRUE;
        }
      }
      if ($input->getOption('exclude')) {
        $excludes = explode(',', $input->getOption('exclude'));
        foreach ($excludes as $exclude) {
          $default_params[$exclude] = FALSE;
        }
      }

      // We know what must be done!
      if ($output->isVerbose()) {
        \Civi\Cv\Cv::io()->table(
          ['target', 'enabled'],
          ArrayUtil::mapKV($default_params, fn($k, $v) => [$k, $v ? 'yes' : 'no'])
        );
      }
      \Civi::rebuild($default_params)->execute();
      return 0;
    }
    else {
      // the old way
      $params = array();
      if ($input->getOption('triggers')) {
        $params['triggers'] = TRUE;
      }
      $result = VerboseApi::callApi3Success('System', 'flush', $params);
      return empty($result['is_error']) ? 0 : 1;
    }
  }

}
