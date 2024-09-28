<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class SqlCliCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testSqlPipe() {
    $query = 'select id, display_name from civicrm_contact limit 1;';
    $p = Process::runOk($this->cv("sql")->setInput($query));
    $this->assertMatchesRegularExpression('/id\s+display_name/', $p->getOutput());
  }

}
