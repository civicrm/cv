<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Civi\Cv\Util\Pop;
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
      ->addArgument('file', InputArgument::REQUIRED, 'yaml file with entities to populate?')
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
    $pop->process($input->getArgument('file'));
  }



}
