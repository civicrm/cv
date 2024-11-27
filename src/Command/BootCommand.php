<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BootCommand extends CvCommand {

  protected function configure() {
    $this
      ->setName('php:boot')
      ->setDescription('Generate PHP bootstrap code');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    switch ($input->getOption('level')) {
      case 'classloader':
        $code = implode("\n", [
          sprintf('$GLOBALS["civicrm_root"] = %s;', var_export(rtrim($GLOBALS["civicrm_root"], '/'), 1)),
          'require_once $GLOBALS["civicrm_root"] . "/CRM/Core/ClassLoader.php";',
          '\CRM_Core_ClassLoader::singleton()->register();',
        ]);
        break;

      case 'settings':
        $code = \Civi\Cv\Bootstrap::singleton()->generate()
          . '\CRM_Core_Config::singleton(FALSE);';
        break;

      case 'full':
        $code = \Civi\Cv\Bootstrap::singleton()->generate()
          . '\CRM_Core_Config::singleton();'
          . '\CRM_Utils_System::loadBootStrap(array(), FALSE);';
        break;

      case 'cms-full':
        $code = \Civi\Cv\CmsBootstrap::singleton()->generate(['bootCms', 'bootCivi']);
        break;

      case 'none':
        $code = '';
        break;

      default:
        throw new \Exception("Cannot generate boot instructions for given boot level.");
    }

    $output->writeln('/*BEGINPHP*/');
    $output->writeln($code);
    $output->writeln('/*ENDPHP*/');
    return 0;
  }

}
