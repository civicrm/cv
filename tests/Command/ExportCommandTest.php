<?php
namespace Civi\Cv\Command;

class ExportCommandTest extends \Civi\Cv\CivilTestCase {
  public function setup() {
    parent::setup();
  }

  /**
   * If the fixturePath has no git repos, then the export lists no details.
   */
  public function testExport_noOutput() {
    $commandTester = $this->createCommandTester(array(
      'command' => 'export',
      'path' => array($this->fixturePath),
    ));
    $json = $commandTester->getDisplay(FALSE);
    $actualDoc = json_decode($json, TRUE);
    $this->assertEquals(realpath($this->fixturePath), realpath($actualDoc['root']));
    $this->assertTrue(!empty($actualDoc['timestamp']));
    $this->assertEquals(array(), $actualDoc['details']);
  }

  /**
   * If the fixutrePath has multiple repos, then export lists details for each.
   */
  public function testExport_output() {
    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1');
    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1/repo-1b');
    $this->createExampleRepo($this->fixturePath . '/example-2/repo-2');

    $commandTester = $this->createCommandTester(array(
      'command' => 'export',
      'path' => array($this->fixturePath),
    ));
    $json = $commandTester->getDisplay(FALSE);
    $actualDoc = json_decode($json, TRUE);
    $this->assertEquals(realpath($this->fixturePath), realpath($actualDoc['root']));
    $this->assertTrue(!empty($actualDoc['timestamp']));
    $this->assertEquals(3, count($actualDoc['details']));

    $expectPaths = array('example-1/repo-1', 'example-1/repo-1/repo-1b', 'example-2/repo-2');

    foreach ($actualDoc['details'] as $details) {
      $this->assertTrue(in_array($details['path'], $expectPaths), 'Expect path: ' . print_r(array(
        'actual' => $details['path'],
        'expected' => $expectPaths,
      ), TRUE));
      $this->assertIsCommit($details['commit']);
      $this->assertTrue(array_key_exists('localBranch', $details));
      $this->assertTrue(array_key_exists('upstreamBranch', $details));
      $this->assertTrue(is_array($details['remotes']));
      $expectPaths = array_diff($expectPaths, array($details['path']));
    }
    $this->assertEquals(array(), $expectPaths);
  }
}
