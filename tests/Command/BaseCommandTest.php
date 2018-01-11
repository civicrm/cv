<?php
namespace Civi\Cv\Command;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;

/**
 * @group std
 */
class BaseCommandTest extends \Civi\Cv\CivilTestCase {

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
  public function testParseOptionalOption($inputArgv, $expectValue) {
    $c = new BaseCommand('ext:example');
    $c->addOption('refresh', array('r'), InputOption::VALUE_OPTIONAL, 'auto');

    $input = new ArgvInput($inputArgv, $c->getDefinition());
    $this->assertEquals($expectValue, $c->parseOptionalOption($input, array('-r', '--refresh'), 'auto', 'yes'));
  }

}
