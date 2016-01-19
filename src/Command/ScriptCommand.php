<?php
namespace Civi\Cv\Command;

use Civi\Cv\BuildkitReader;
use Civi\Cv\GitRepo;
use Civi\Cv\Util\ArrayUtil;
use Civi\Cv\Util\Filesystem;
use Civi\Cv\Util\Process as ProcessUtil;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ScriptCommand extends BaseCommand {

  /**
   * @var Filesystem
   */
  var $fs;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('php-script')
      ->setAliases(array('scr'))
      ->setDescription('Execute a PHP script')
      ->addArgument('script');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    \Civi\Cv\Bootstrap::singleton()->boot();
    \CRM_Core_Config::singleton();
    \CRM_Utils_System::loadBootStrap(array(), FALSE);

    require $input->getArgument('script');
  }

}
