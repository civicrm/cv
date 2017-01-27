<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Civi\Pop\Pop;
use Faker;

class PopCommand extends BaseCommand {

  /**
   * @var array
   */
  var $defaults;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    $this->defaults = array('version' => 3);
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('pop')
      ->addArgument('file', InputArgument::REQUIRED, 'yaml file with entities to populate')
      ->addOption('out', NULL, InputOption::VALUE_REQUIRED, 'Output format (' . implode(',', Encoder::getFormats()) . ')', Encoder::getDefaultFormat())
      ->setDescription('Populate a site with entities from a yaml file')
      ->setHelp('Populate a site with entities from a yaml file

Examples:
  cv pop contacts.yml

NOTE: See doc/pop.md for usage
');
    parent::configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->boot($input, $output);
    $pop = new Pop($output);
    $pop->setInteractive($input->isInteractive());
    if ($input->getOption('out') != 'json-pretty') {
      $pop->setInteractive(0);
    }
    var_dump();
    $fs = new Filesystem;
    if($fs->isAbsolutePath($input->getArgument('file'))){
      $pop->process($input->getArgument('file'));
    }else{
      $pop->process($_SERVER['PWD']. DIRECTORY_SEPARATOR . $input->getArgument('file'));
    }

    if ($input->getOption('out') != 'json-pretty') {
      $this->sendResult($input, $output, $pop->getSummary());
    }
  }

}
