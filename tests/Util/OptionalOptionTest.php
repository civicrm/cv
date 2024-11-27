<?php
namespace Civi\Cv\Command;

use Civi\Cv\Cv;
use Civi\Cv\Util\OptionalOption;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @group std
 */
class OptionalOptionTest extends \Civi\Cv\CivilTestCase {

  public function refreshExamples() {
    $cases = array();
    $cases[] = array(
      array('examplecmd'),
      'auto',
    );
    $cases[] = array(
      array('examplecmd', '-r'),
      'yes',
    );
    $cases[] = array(
      array('examplecmd', '--refresh'),
      'yes',
    );
    $cases[] = array(
      array('examplecmd', '--refresh=maybe'),
      'maybe',
    );
    $cases[] = array(
      array('examplecmd', '--refresh=no'),
      'no',
    );
    return $cases;
  }

  /**
   * @param array $inputArgv
   * @param string $expectValue
   * @dataProvider refreshExamples
   */
  public function testParse($inputArgv, $expectValue) {
    Cv::ioStack()->push(...$this->createInputOutput($inputArgv));
    try {
      $this->assertEquals($expectValue, OptionalOption::parse(Cv::input(), ['-r', '--refresh'], 'auto', 'yes'));
    }
    finally {
      Cv::ioStack()->pop();
    }
  }

  /**
   * @return array
   *   [0 => InputInterface, 1 => OutputInterface]
   */
  protected function createInputOutput(?array $argv = NULL): array {
    $input = new ArgvInput($argv);
    $input->setInteractive(FALSE);
    $output = new NullOutput();
    return [$input, $output];
  }

}
