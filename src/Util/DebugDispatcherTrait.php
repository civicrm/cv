<?php
namespace Civi\Cv\Util;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait DebugDispatcherTrait {

  /**
   * Extract the full list of available event names.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   * @param string $eventFilter
   *   Filter the list of events by name. This can be:
   *   - Exact name, e.g. 'hook_civicrm_foo'
   *   - Regex, e.g. '/^hook_/'
   * @return array
   *   Ex: array('hook_civicrm_fo', 'hook_civicrm_bar')
   */
  public function findEventNames($dispatcher, $eventFilter) {
    if (!$eventFilter) {
      $listenersByEvent = $dispatcher->getListeners();
    }
    elseif ($eventFilter{0} === '/') {
      $listenersByEvent = array();
      foreach ($dispatcher->getListeners() as $e => $ls) {
        if (preg_match($eventFilter, $e)) {
          $listenersByEvent[$e] = $ls;
        }
      }
    }
    else {
      $listenersByEvent = array($eventFilter => $dispatcher->getListeners($eventFilter));
    }

    $eventNames = array_keys($listenersByEvent);
    sort($eventNames);
    return $eventNames;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   * @param array $eventNames
   */
  public function printEventListeners(OutputInterface $output, $dispatcher, $eventNames) {
    foreach ($eventNames as $event) {
      $rows = array();
      $i = 0;
      foreach ($dispatcher->getListeners($event) as $listener) {
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
        elseif (is_string($listener)) {
          $handled = TRUE;
          $rows[] = array('#' . ++$i, $listener . '()');
        }
        else {
          $f = new \ReflectionFunction($listener);
          $rows[] = array(
            '#' . ++$i,
            'closure(' . $f->getFileName() . '@' . $f->getStartLine() . ')',
          );
          $handled = TRUE;
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
