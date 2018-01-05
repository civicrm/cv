<?php
namespace Civi\Cv\Util;

use Civi\Cv\Encoder;
use Civi\Cv\Json;
use Civi\Cv\SiteConfigReader;
use Civi\Cv\Util\ArrayUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This trait can be mixed into a Symfony `Command` to provide Civi/CMS-bootstrap options.
 *
 * It first boots the CMS and then boots Civi.
 */
trait CmsBootTrait {

  protected function configureBootOptions() {
    // TODO $this->addOption('level', NULL, InputOption::VALUE_REQUIRED, 'Bootstrap level (none,classloader,settings,full)', 'full');
    // TODO $this->addOption('test', 't', InputOption::VALUE_NONE, 'Bootstrap the test database (CIVICRM_UF=UnitTests)');
    $this->addOption('user', 'U', InputOption::VALUE_REQUIRED, 'CMS user');
  }

  private $bootstrap;

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Civi\Cv\CmsBootstrap
   */
  protected function boot(InputInterface $input, OutputInterface $output) {
    $output->writeln('<info>[CmsBootTrait]</info> Start', OutputInterface::VERBOSITY_DEBUG);
    $this->bootCms($input, $output);
    $this->bootCivi($input, $output);
    $output->writeln('<info>[CmsBootTrait]</info> Finished', OutputInterface::VERBOSITY_DEBUG);

    return $this->getBootstrap($input, $output);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Civi\Cv\CmsBootstrap
   */
  protected function bootCms(InputInterface $input, OutputInterface $output) {
    $bootstrap = $this->getBootstrap($input, $output);
    $output->writeln('<info>[CmsBootTrait]</info> Call CMS bootstrap', OutputInterface::VERBOSITY_DEBUG);
    $bootstrap->bootCms();

    return $bootstrap;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Civi\Cv\CmsBootstrap
   */
  protected function bootCivi(InputInterface $input, OutputInterface $output) {
    $bootstrap = $this->getBootstrap($input, $output);
    $output->writeln('<info>[CmsBootTrait]</info> Call Civi bootstrap', OutputInterface::VERBOSITY_DEBUG);
    $bootstrap->bootCivi();

    return $bootstrap;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return \Civi\Cv\CmsBootstrap
   */
  protected function getBootstrap(InputInterface $input, OutputInterface $output) {
    if ($this->bootstrap) {
      return $this->bootstrap;
    }

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
        'output' => $output,
      );
    }
    else {
      $boot_params = array();
    }

    if ($input->getOption('user')) {
      $boot_params['user'] = $input->getOption('user');
    }

    $this->bootstrap = \Civi\Cv\CmsBootstrap::singleton()->addOptions($boot_params);
    return $this->bootstrap;
  }

}
