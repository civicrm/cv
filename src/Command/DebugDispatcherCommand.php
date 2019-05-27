<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugDispatcherCommand extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;

  protected function configure() {
    $this
      ->setName('debug:event-dispatcher')
      ->setDescription('Dump the list of event listeners')
      ->addArgument('event', InputArgument::OPTIONAL, 'An event name or regex')
      // ->addOption('out', NULL, InputArgument::OPTIONAL, 'Specify return format (json,none,php,pretty,shell)', \Civi\Cv\Encoder::getDefaultFormat())
      // ->configureOutputOptions()
      ->setHelp('
Dump the list of event listeners

Examples:
  cv debug:event-dispatcher
  cv debug:event-dispatcher actionSchedule.getMappings
  cv debug:event-dispatcher /^actionSchedule/
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    define('CIVICRM_CONTAINER_CACHE', 'never');
    $output->getErrorOutput()->writeln('<comment>The debug command ignores the container cache.</comment>');
    $this->boot($input, $output);

    $container = \Civi::container();

    /*
     * Workaround: Ensure that API kernel has registered its event listeners.
     *
     * At time of writing (Mar 2017), civicrm-core has its list of
     * event-listeners in two places: Container::createEventDispatcher() and
     * Container::createApiKernel(). That's ugly. In the long term, both of
     * these should be replaced with a more distributed+consistent mechanism
     * (e.g. `services.yml`). However, in the mean-time, we need to ensure
     * that the API kernel (if applicable) has its chance to register listeners.
     */
    if ($container->has('civi_api_kernel')) {
      $container->get('civi_api_kernel');
    }

    $dispatcher = $container->get('dispatcher');
    $eventFilter = $input->getArgument('event');
    $eventNames = $this->findEventNames($dispatcher, $eventFilter);
    $this->printEventListeners($output, $dispatcher, $eventNames);
  }

  use \Civi\Cv\Util\DebugDispatcherTrait;

}
