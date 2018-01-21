<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DebugContainerCommand extends BaseCommand {

  use \Civi\Cv\Util\BootTrait;

  protected function configure() {
    $this
      ->setName('debug:container')
      ->setDescription('Dump the container configuration')
      ->addArgument('path')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getTabularFormats()) . ')', Encoder::getDefaultFormat('table'))
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
      if ($definition->getFactoryClass() || $definition->getFactoryMethod() || $definition->getFactoryService()) {
        $extras[] = 'factory';
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
