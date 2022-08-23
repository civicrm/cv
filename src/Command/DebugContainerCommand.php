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
      $filter = function ($name, $definition) {
        return TRUE;
      };
    }
    elseif ($filterPat[0] === '/') {
      $filter = function ($name, $definition) use ($filterPat) {
        return (bool) preg_match($filterPat, $name);
      };
    }
    else {
      $filter = function ($name, $definition) use ($filterPat) {
        return $name === $filterPat;
      };
    }

    if (!$input->getOption('all')) {
      $filter = function ($name, Definition $definition) use ($filter) {
        return $definition->isPublic() && $filter($name, $definition);
      };
    }

    $rows = array();
    $definitions = $c->getDefinitions();
    ksort($definitions);
    foreach ($definitions as $name => $definition) {
      if (!$filter($name, $definition)) {
        continue;
      }

      $events = '?';
      if (class_exists('Civi\Core\Event\EventScanner')) {
        if ($definition->getTag('event_subscriber') || $definition->getTag('kernel.event_subscriber')) {
          $events = \Civi\Core\Event\EventScanner::findListeners($definition->getClass());
        }
        else {
          $events = '';
        }
      }

      $extras = array();
      foreach (['getFactoryClass', 'getFactoryMethod', 'getFactoryService', 'getFactory'] as $factoryCheck) {
        if (is_callable([$definition, $factoryCheck]) && $definition->$factoryCheck()) {
          $extras[] = 'factory';
        }
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

}
