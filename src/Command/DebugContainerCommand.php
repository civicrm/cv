<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\BootTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Definition;

class DebugContainerCommand extends BaseCommand {

  use BootTrait;
  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('debug:container')
      ->setDescription('Dump the container configuration')
      ->addArgument('name', InputArgument::OPTIONAL, 'An service name or regex')
      ->addOption('concrete', 'C', InputOption::VALUE_NONE, 'Display concrete class names. (This requires activating every matching service.)')
      ->addOption('all', 'a', InputOption::VALUE_NONE, 'Display all services. (Disable container-optimizations which hide internal services.)')
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'table'])
      ->setHelp('
Dump the container configuration
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    define('CIVICRM_CONTAINER_CACHE', 'never');
    $output->getErrorOutput()->writeln('<comment>The debug command ignores the container cache.</comment>');
    $this->boot($input, $output);

    $c = $this->getInspectableContainer($input->getOption('all'));

    $filterPat = $input->getArgument('name');
    if (empty($filterPat)) {
      $filter = function (Definition $definition, string $name) {
        return TRUE;
      };
    }
    elseif ($filterPat[0] === '/') {
      $filter = function (Definition $definition, string $name) use ($filterPat) {
        return (bool) preg_match($filterPat, $name);
      };
    }
    else {
      $filter = function (Definition $definition, string $name) use ($filterPat) {
        return $name === $filterPat;
      };
    }

    if (!$input->getOption('all')) {
      $filter = function (Definition $definition, string $name) use ($filter) {
        return $definition->isPublic() && $filter($definition, $name);
      };
    }

    $definitions = $c->getDefinitions();
    $definitions = array_filter($definitions, $filter, ARRAY_FILTER_USE_BOTH);
    ksort($definitions);

    if ($output->isVerbose()) {
      $this->showVerboseReport($input, $output, $definitions);
    }
    else {
      $this->showBasicReport($input, $output, $definitions);
    }
  }

  public function showBasicReport(InputInterface $input, OutputInterface $output, array $definitions): void {
    $rows = [];

    foreach ($definitions as $name => $definition) {
      $events = $this->getEvents($definition);

      $extras = array();
      if ($this->getFactory($definition) !== NULL) {
        $extras[] = 'factory';
      }
      if ($definition->getMethodCalls()) {
        $extras[] = sprintf("calls[%s]", count($definition->getMethodCalls()));
      }
      foreach ($definition->getTags() as $tag => $tagData) {
        $extras[] = sprintf("tag[%s]", $tag);
      }
      $class = $input->getOption('concrete') ? get_class(\Civi::service($name)) : $definition->getClass();

      $rows[] = array('service' => $name, 'class' => $class, 'events' => $events ? count($events) : '', 'extras' => implode(' ', $extras));
    }

    $this->sendTable($input, $output, $rows, array('service', 'class', 'events', 'extras'));
  }

  public function showVerboseReport(InputInterface $input, OutputInterface $output, array $definitions): void {
    foreach ($definitions as $name => $definition) {
      $row = [];
      $row[] = ['key' => 'service', 'value' => $name];
      $row[] = ['key' => 'class', 'value' => $input->getOption('concrete') ? get_class(\Civi::service($name)) : $definition->getClass()];
      foreach ($definition->getTags() as $tag => $tagData) {
        $row[] = ['key' => "tag[$tag]", 'value' => $tagData];
      }

      foreach ($this->getEvents($definition) as $eventName => $eventValue) {
        $row[] = ['key' => "event[$eventName]", 'value' => $eventValue];
      }
      if ($factory = $this->getFactory($definition)) {
        $row[] = ['key' => 'factory', 'value' => $factory];
      }
      foreach ($definition->getMethodCalls() as $n => $call) {
        $row[] = ['key' => "call[$n]", 'value' => $call];
      }

      $this->sendTable($input, $output, $row);
    }
  }

  /**
   * @param bool $isAll
   * @return \Symfony\Component\DependencyInjection\ContainerBuilder
   * @throws \CRM_Core_Exception
   */
  protected function getInspectableContainer(bool $isAll): \Symfony\Component\DependencyInjection\ContainerBuilder {
    // To probe definitions, we need access to the raw ContainerBuilder.
    $container = (new \Civi\Core\Container())->createContainer();
    if (version_compare(\CRM_Utils_System::version(), '4.7.0', '<')) {
      throw new \RuntimeException("Container inspection is only supported on 4.7.0+.");
    }

    if (is_callable([$container, 'getCompilerPassConfig'])) {
      $passConfig = $container->getCompilerPassConfig();
      $removingPasses = array_filter($passConfig->getRemovingPasses(), function ($pass) {
        return !($pass instanceof \Symfony\Component\DependencyInjection\Compiler\InlineServiceDefinitionsPass);
      });
      $passConfig->setRemovingPasses($removingPasses);
    }

    $container->compile();
    return $container;
  }

  /**
   * @param $definition
   *
   * @return array|string
   */
  protected function getEvents($definition) {
    if (class_exists('Civi\Core\Event\EventScanner')) {
      if ($definition->getTag('event_subscriber') || $definition->getTag('kernel.event_subscriber')) {
        return \Civi\Core\Event\EventScanner::findListeners($definition->getClass());
      }
      else {
        return '';
      }
    }
    return '?';
  }

  /**
   * @param \Symfony\Component\DependencyInjection\Definition $definition
   * @return array|string|null
   *   A value describing the factory, or NULL if there is none.
   */
  protected function getFactory(Definition $definition) {
    if (is_callable([$definition, 'getFactory'])) {
      return $definition->getFactory();
    }
    foreach (['getFactoryClass', 'getFactoryMethod', 'getFactoryService'] as $factoryCheck) {
      if (is_callable([$definition, $factoryCheck]) && $definition->$factoryCheck()) {
        return 'yes';
      }
    }
    return NULL;
  }

}
