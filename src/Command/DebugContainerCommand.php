<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugContainerCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('debug:container')
      ->setDescription('Dump the container configuration')
      ->addArgument('path')
      // ->addOption('out', NULL, InputArgument::OPTIONAL, 'Specify return format (json,none,php,pretty,shell)', \Civi\Cv\Encoder::getDefaultFormat())
      ->setHelp('
Dump the container configuration
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);

    $z = new \Civi\Core\Container();
    $c = $z->createContainer();
    $c->compile();

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

      $rows[] = array($name, $definition->getClass(), implode(' ', $extras));
    }

    $table = new Table($output);
    $table->setHeaders(array('Service', 'Class', 'Extras'));
    $table->addRows($rows);
    $table->render();
  }

}
