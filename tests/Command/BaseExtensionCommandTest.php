<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\ExtensionTrait;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @group std
 * @group ext
 */
class BaseExtensionCommandTest extends \Civi\Cv\CivilTestCase {

  public function repoOptionExamples() {
    $cases = array();
    $cases[] = array(
      array('examplecmd'),
      'https://civicrm.org/extdir/ver={ver}|uf={uf}|status=stable|ready=ready',
    );
    $cases[] = array(
      array('examplecmd', '--filter-uf=Drupal8'),
      'https://civicrm.org/extdir/ver={ver}|uf=Drupal8|status=stable|ready=ready',
    );
    $cases[] = array(
      array('examplecmd', '--dev'),
      'https://civicrm.org/extdir/ver={ver}|uf={uf}|status=|ready=',
    );
    $cases[] = array(
      array('examplecmd', '--filter-status=*'),
      'https://civicrm.org/extdir/ver={ver}|uf={uf}|status=|ready=ready',
    );
    return $cases;
  }

  /**
   * @param array $inputArgv
   * @param string $expectUrl
   * @dataProvider repoOptionExamples
   */
  public function testParseRepo($inputArgv, $expectUrl) {
    $c = new class('ext:example') extends CvCommand {
      use ExtensionTrait;
    };
    $c->configureRepoOptions();

    $input = new ArgvInput($inputArgv, $c->getDefinition());
    $this->assertEquals($expectUrl, $c->parseRepoUrl($input));
  }

}
