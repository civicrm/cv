<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugDispatcherCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('debug:event-dispatcher')
      ->setDescription('Dump the list of event listeners')
      ->addArgument('event', InputArgument::OPTIONAL, 'An event name or regex')
      // ->addOption('out', NULL, InputArgument::OPTIONAL, 'Specify return format (json,none,php,pretty,shell)', \Civi\Cv\Encoder::getDefaultFormat())
      ->setHelp('
Dump the list of event listeners

Examples:
  cv debug:event-dispatcher
  cv debug:event-dispatcher actionSchedule.getMappings
  cv debug:event-dispatcher /^actionSchedule/
');
    parent::configureBootOptions();
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

    $d = $container->get('dispatcher');

    $eventFilter = $input->getArgument('event');
    if (!$eventFilter) {
      $listenersByEvent = $d->getListeners();
    }
    elseif ($eventFilter{0} === '/') {
      $listenersByEvent = array();
      foreach ($d->getListeners() as $e => $ls) {
        if (preg_match($eventFilter, $e)) {
          $listenersByEvent[$e] = $ls;
        }
      }
    }
    else {
      $listenersByEvent = array($eventFilter => $d->getListeners($eventFilter));
    }

    ksort($listenersByEvent);
    foreach ($listenersByEvent as $event => $rawListeners) {
      $rows = array();
      $i = 0;
      foreach ($d->getListeners($event) as $listener) {
        $handled = FALSE;
        if (is_array($listener)) {
          list ($a, $b) = $listener;
          if (is_object($a)) {
            $rows[] = array('#' . ++$i, get_class($a) . "->$b()");
            $handled = TRUE;
          }
          elseif (is_string($a)) {
            $rows[] = array('#' . ++$i, "$a::$b()");
            $handled = TRUE;
          }
        }
        if (!$handled) {
          $rows[] = array('#' . ++$i, "unidentified");
        }
      }

      $output->writeln("<info>[Event]</info> $event");
      $table = new Table($output);
      $table->setHeaders(array('Order', 'Callable'));
      $table->addRows($rows);
      $table->render();
      $output->writeln("");
    }
  }

}
