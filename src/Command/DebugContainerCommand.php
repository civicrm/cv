<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Relativizer;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DebugContainerCommand extends CvCommand {

  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('service')
      ->setAliases(['svc'])
      ->setDescription('Inspect the service container')
      ->addArgument('name', InputArgument::OPTIONAL, 'An service name or regex')
      ->addOption('concrete', 'C', InputOption::VALUE_NONE, 'Display concrete class names. (This requires activating every matching service.)')
      ->addOption('internal', 'i', InputOption::VALUE_NONE, 'Include internal services')
      ->addOption('tag', NULL, InputOption::VALUE_REQUIRED, 'Find services by tag.')
      ->configureOutputOptions(['tabular' => TRUE, 'fallback' => 'table', 'defaultColumns' => 'service,class,events,extras', 'shortcuts' => ['table', 'list', 'json', 'all']])
      ->setHelp('
Dump the container configuration

NOTE: By default, internal services are not displayed. However, some flags will enable display of
internal services (eg `--all`, `--tag=XXX`, or `-v`).
');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    define('CIVICRM_CONTAINER_CACHE', 'never');
    $output->getErrorOutput()->writeln('<comment>The debug command ignores the container cache.</comment>');
    parent::initialize($input, $output);
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $c = $this->getInspectableContainer($input);

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

    if ($output->isVerbose() || $input->getOption('tag')) {
      $input->setOption('internal', TRUE);
    }

    if ($input->getOption('tag')) {
      $filter = function (Definition $definition, string $name) use ($filter, $input) {
        return $definition->getTag($input->getOption('tag')) && $filter($definition, $name);
      };
    }

    if (!$input->getOption('internal')) {
      $filter = function (Definition $definition, string $name) use ($filter) {
        if (!$definition->isPublic() || $definition->getTag('internal')) {
          return FALSE;
        }
        return $filter($definition, $name);
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
    return 0;
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
      if ($definition->getTags()) {
        $extras[] = sprintf("tag[%s]", implode(' ', array_keys($definition->getTags())));
      }
      $class = $input->getOption('concrete') ? get_class(\Civi::service($name)) : $definition->getClass();

      $rows[] = array('service' => $name, 'class' => $class, 'events' => $events ? count($events) : '', 'extras' => implode(' ', $extras));
    }

    $this->sendStandardTable($rows);
  }

  public function showVerboseReport(InputInterface $input, OutputInterface $output, array $definitions): void {
    $relativizer = new Relativizer();
    foreach ($definitions as $name => $definition) {
      /** @var \Symfony\Component\DependencyInjection\Definition $definition */
      $class = ($input->getOption('concrete') && $definition->isPublic())
        ? get_class(\Civi::service($name))
        : $definition->getClass();

      if (class_exists($class)) {
        $classFile = (new \ReflectionClass($class))->getFileName();
        $classFile = $input->getOption('concrete') ? $classFile : $relativizer->filter($classFile);
      }
      else {
        $classFile = '?';
      }

      $row = [];
      $row[] = ['key' => 'service', 'value' => $name];
      $row[] = ['key' => 'class', 'value' => $class];
      $row[] = ['key' => 'class-file', 'value' => $classFile];
      foreach ($definition->getTags() as $tag => $tagData) {
        $row[] = ['key' => "tag[$tag]", 'value' => $tagData];
      }
      foreach ($this->getEvents($definition) as $eventName => $eventValue) {
        $row[] = ['key' => "event[$eventName]", 'value' => $this->toPrintableData($eventValue)];
      }
      if ($factory = $this->getFactory($definition)) {
        $row[] = ['key' => 'factory', 'value' => $this->toPrintableData($factory)];
      }
      foreach ($definition->getMethodCalls() as $n => $call) {
        $row[] = ['key' => "call", 'value' => $this->toPrintableData($call)];
      }

      $this->sendTable($input, $output, $row);
    }
  }

  protected function toPrintableData($item) {
    if ($item instanceof Reference) {
      return '$(' . $item . ')';
    }
    elseif ($item instanceof ServiceClosureArgument) {
      return $this->toPrintableData($item->getValues());
    }
    elseif (is_array($item)) {
      $r = [];
      foreach ($item as $key => $value) {
        $r[$key] = $this->toPrintableData($value);
      }
      return $r;
    }
    elseif (is_scalar($item) || $item instanceof \stdClass) {
      return $item;
    }

    return '(' . gettype($item) . ')';
  }

  /**
   * @parm \Symfony\Component\Console\Input\InputInterface $input
   * @return \Symfony\Component\DependencyInjection\ContainerBuilder
   * @throws \CRM_Core_Exception
   */
  protected function getInspectableContainer(InputInterface $input): \Symfony\Component\DependencyInjection\ContainerBuilder {
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
   * @param \Symfony\Component\DependencyInjection\Definition $definition
   * @return array|string
   */
  protected function getEvents($definition): array {
    if (class_exists('Civi\Core\Event\EventScanner')) {
      if ($definition->getTag('event_subscriber') || $definition->getTag('kernel.event_subscriber')) {
        return \Civi\Core\Event\EventScanner::findListeners($definition->getClass());
      }
    }
    return [];
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
