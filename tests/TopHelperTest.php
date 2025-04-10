<?php
namespace Civi\Cv;

use Civi\Cv\Util\Process;

/**
 * The "Top" helper provides access to top-level class-names, even if you have namespace prefixing.
 * To ensure that it works, we need distinct tests for PHAR and SRC runtimes.
 *
 * @group std
 * @group php
 */
class TopHelperTest extends \Civi\Cv\CivilTestCase {

  public function testTop() {
    if ($this->isCvPharTest()) {
      $exprs = [
        'Cvphar\Fruit\Apple' => '\Fruit\Apple',
        '\Cvphar\Fruit\Banana' => '\Fruit\Banana',
        'Fruit\Cherry' => '\Fruit\Cherry',
        '\Fruit\Date' => '\Fruit\Date',
      ];
    }
    else {
      $exprs = [
        'Fruit\Apple' => '\Fruit\Apple',
        '\Fruit\Banana' => '\Fruit\Banana',
      ];
    }

    foreach ($exprs as $input => $expected) {
      $p = Process::runOk($this->cv("ev 'return \Civi\Cv\Top::symbol(getenv(\"SYMBOL\"));'")
        ->setEnv(['SYMBOL' => $input]));
      $actual = json_decode($p->getOutput());
      $this->assertEquals($expected, $actual, "Input ($input) should yield value ($expected).");
    }
  }

}
