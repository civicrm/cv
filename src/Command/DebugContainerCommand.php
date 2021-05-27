<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\BootTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugContainerCommand extends BaseCommand {

  use BootTrait;
  use StructuredOutputTrait;

  protected function configure() {
    $this
      ->setName('debug:container')
      ->setDescription('Dump the container configuration')
      ->addArgument('path')
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

    // To probe definitions, we need access to the raw ContainerBuilder.
    $z = new \Civi\Core\Container();
    $c = $z->createContainer();
    if (version_compare(\CRM_Utils_System::version(), '4.7.0', '>=')) {
      $c->compile();
    }

    $rows = array();
    $definitions = $c->getDefinitions();
    ksort($definitions);
    foreach ($definitions as $name => $definition) {
      $extras = array();
      foreach (['getFactoryClass', 'getFactoryMethod', 'getFactoryService', 'getFactory'] as $factoryCheck) {
        if (is_callable([$definition, $factoryCheck]) && $definition->$factoryCheck()) {
          $extras[] = 'factory';
        }
      }
      if ($definition->getMethodCalls()) {
        $extras[] = sprintf("calls[%s]", count($definition->getMethodCalls()));
      }
      if ($definition->getTags()) {
        $extras[] = sprintf("tags[%s]", count($definition->getTags()));
      }

      $rows[] = array('service' => $name, 'class' => $definition->getClass(), 'extras' => implode(' ', $extras));
    }

    $this->sendTable($input, $output, $rows, array('service', 'class', 'extras'));
  }

}
