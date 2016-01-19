<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseCommand extends Command {

  protected function boot(InputInterface $input, OutputInterface $output) {
    \Civi\Cv\Bootstrap::singleton()->boot();
    \CRM_Core_Config::singleton();
    \CRM_Utils_System::loadBootStrap(array(), FALSE);
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param $result
   */
  protected function sendResult(InputInterface $input, OutputInterface $output, $result) {
    $outMode = $input->getOption('out');
    switch ($outMode) {
      case 'none':
        break;

      case 'pretty':
        $output->writeln(print_r($result, 1));
        break;

      case 'php':
        $output->writeln(var_export($result, 1));
        break;

      case 'json':
        $options = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0;
        $output->writeln(json_encode($result, $options));
        break;

      case 'shell':
        if (is_scalar($result)) {
          $output->writeln(escapeshellarg($result));
        }
        //elseif (is_array($result)) {
        //  // This works for assoc-arrays but not numerical arrays.
        //  $data = ArrayUtil::implodeTree('_', $result);
        //  foreach ($data as $k => $v) {
        //    $output->writeln(sprintf("%s=%s", $k, escapeshellarg($v)));
        //  }
        //}
        else {
          $output->writeln(sprintf("<error>%s</error>", gettype($result)));
        }
        break;

      default:
        throw new \RuntimeException('Unknown output format');
    }
  }

}
