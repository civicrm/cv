<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class Api4CommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testDefaultData() {
    $p = Process::runOk($this->cv("api4 Contact.get +s id,display_name +l 3@6"));
    $rows = json_decode($p->getOutput(), 1);
    $this->assertEquals(3, count($rows), 'Result should have the right number of records');
    foreach ($rows as $row) {
      $this->assertNotEmpty($row['id'], 'Each record should have id');
      $this->assertNotEmpty($row['display_name'], 'Each record should have display_name');
      $this->assertEquals(['id', 'display_name'], array_keys($row), 'No other properties should be returned');
    }
  }

  public function testMetaProps() {
    $p = Process::runOk($this->cv("api4 Contact.get +s id,display_name +l 3@6 -M"));
    $result = json_decode($p->getOutput(), 1);
    $this->assertEquals('Contact', $result['entity'], 'Result should have meta property: entity');
    $this->assertEquals('get', $result['action'], 'Result should have meta property: action');
    $this->assertEquals(3, $result['count'], 'Result should have meta property: count');
    $this->assertEquals(3, count($result['values']), 'Result should have the right number of records');
    foreach ($result['values'] as $row) {
      $this->assertNotEmpty($row['id'], 'Each record should have id');
      $this->assertNotEmpty($row['display_name'], 'Each record should have display_name');
      $this->assertEquals(['id', 'display_name'], array_keys($row), 'No other properties should be returned');
    }
  }

  public function testMetaPropsLimited() {
    $p = Process::runOk($this->cv("api4 Contact.get +s id,display_name +l 3@6 -m entity,countFetched"));
    $result = json_decode($p->getOutput(), 1);
    $this->assertEquals('Contact', $result['entity'], 'Result should have meta property: entity');
    $this->assertFalse(isset($result['action']));
    $this->assertEquals(3, $result['countFetched'], 'Result should have meta property: countFetched');
    $this->assertFalse(isset($result['values']));
  }

}
