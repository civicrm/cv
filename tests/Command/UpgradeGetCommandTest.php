<?php
namespace Civi\Cv\Command;

class UpgradeGetCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testGetStable() {
    $data = $this->upgradeRun('stable');
    $this->assertMatchesRegularExpression('/\/civicrm-' . $data['version'] . '-(drupal[68]?|joomla|wordpress)+\.(tar\.gz|zip)$/', $data['url']);
    $this->stableVersionCheck($data);
  }

  public function testGetStableWordpress() {
    $data = $this->upgradeRun('stable', 'WordPress');
    $this->stableVersionCheck($data);
  }

  public function testGetStableJoomla() {
    $data = $this->upgradeRun('stable', 'Joomla');
    $this->stableVersionCheck($data);
  }

  public function testGetStableDrupal() {
    $data = $this->upgradeRun('stable', 'Drupal');
    $this->stableVersionCheck($data);
  }

  public function testGetStableBackdrop() {
    $data = $this->upgradeRun('stable', 'Backdrop');
    $this->stableVersionCheck($data);
  }

  public function testGetNightly() {
    $data = $this->upgradeRun('nightly');
    $revisionId = $this->analyzeRevision($data);
    $this->assertGreaterThanOrEqual((int) date('Ymdhi', strtotime('-2 days')), (int) $revisionId, 'Nightly revision is well over a day old.');
  }

  public function testGetRc() {
    $data = $this->upgradeRun('rc');
    $revisionId = $this->analyzeRevision($data);
  }

  protected function stableVersionCheck($data) {
    $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $data['version']);
    $this->assertEquals($data['version'], $data['rev'], 'Stable revision is not the same as version number.');
  }

  protected function analyzeRevision($data) {
    $this->assertMatchesRegularExpression('/\d+\.\d+\.\d+$/', $data['version']);
    $this->assertMatchesRegularExpression('/' . $data['version'] . '-\d+$/', $data['rev'], 'Revision name does not include version number.');
    $revisionParts = explode('-', $data['rev']);
    array_unshift($revisionParts, 'civicrm');
    $revisionId = array_pop($revisionParts);
    $revisionParts[] = '(drupal[68]?|joomla|wordpress)';
    $revisionParts[] = $revisionId;
    $this->assertMatchesRegularExpression('/\/' . implode('-', $revisionParts) . '\.(tar\.gz|zip)$/', $data['url']);
    $this->assertLessThanOrEqual((int) date('Ymdhi', strtotime('+1 day')), (int) $revisionId, 'Revision is from the future.');
    return $revisionId;
  }

  protected function upgradeRun($stability, $cms = NULL) {
    $cmsString = ($cms) ? " --cms=$cms" : '';
    $p = $this->cv("upgrade:get --stability=$stability$cmsString");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data['url']), 'Looking for url in output: ' . $p->getOutput());
    switch ($cms) {
      case 'WordPress':
      case 'Joomla':
        $this->assertMatchesRegularExpression('/-' . strtolower($cms) . '\.zip/', $data['url']);
        break;

      case 'Drupal':
      case 'Drupal6':
      case 'Drupal8':
      case 'Backdrop':
        $this->assertMatchesRegularExpression('/-' . strtolower($cms) . '\.tar\.gz/', $data['url']);
    }
    $headers = get_headers($data['url']);
    $this->assertContains('200 OK', $headers[0], 'URL does not have the file to download.');
    return $data;
  }

}
