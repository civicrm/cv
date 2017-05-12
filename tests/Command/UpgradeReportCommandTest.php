<?php
namespace Civi\Cv\Command;

class UpgradeGetCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  public function testStartReport() {
    $p = $this->cv("upgrade:report --started");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data['name']), 'Looking for name in output: ' . $p->getOutput());
    $this->assertTrue(isset($data['siteId']), 'Looking for siteId in output: ' . $p->getOutput());
    $this->assertTrue(is_int($data['started']), 'Looking for started timestamp in output: ' . $p->getOutput());
    $this->assertTrue(isset($data['startReport']['values'][0]['version']), 'Looking for version in start report: ' . $p->getOutput());
    $this->assertEquals(0, $p->getExitCode(), 'Start report exits with an error.');
  }

  public function testSetName() {
    $name = '1776521f994630387110d2c79aa0e91e';
    $downloadedTime = 1494615613;
    $p = $this->cv("upgrade:report --downloaded $downloadedTime --downloadurl https://download.civicrm.org/civicrm-4.7.123-drupal9.tar.gz --extracted --name $name");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertEquals($name, $data['name'], 'Report name does not match the provided name.');
    $this->assertEquals($downloadedTime, $data['downloaded'], 'Report downloaded timestamp does not match the provided timestamp.');
    $this->assertGreaterThanOrEqual(strtotime('-5 minutes'), $data['extracted'], 'Extracted timestamp is not current time.');
    $this->assertTrue(empty($data['startReport']), 'A download/status report has `startReport` included.');
    $this->assertEquals(0, $p->getExitCode(), 'Download and extract report exits with an error.');
  }


  // TODO
  // public function testUpgraded() {
  //   $name = '1776521f994630387110d2c79aa0e91e';
  //   $downloadedTime = 1494615613;
  //   $messageArray = array(
  //     'things' => 'stuff',
  //     'otherThings' => array(
  //       'one',
  //       'two',
  //       'three',
  //     ),
  //   );
  //   $messages = json_encode($messageArray);
  //   $p = $this->cv("upgrade:report --upgraded --upgrademessages=/dev/stdin --name $name");
  //   $p->setOptions(array(
  //     'upgrademessages' => $messageArray,
  //   ));
  //   $p->run();
  //   $data = json_decode($p->getOutput(), 1);
  //   var_dump($data['upgradeReport']);
  //   $this->assertEquals($messageArray, json_decode($data['upgradeReport'], TRUE), 'Report data does not match the provided data.');
  // }

  /**
   * Make sure you can't send a report with no mode (e.g. --started, --extracted,
   * etc.)
   */
  public function testRequireMode() {
    $p = $this->cv("upgrade:report");
    $p->run();
    $this->assertGreaterThan(0, $p->getExitCode(), 'Report with no mode does not exit with an error.');
  }

  /**
   * A download report needs a download URL
   */
  public function testRequireDownloadurl() {
    $name = '1776521f994630387110d2c79aa0e91e';
    $p = $this->cv("upgrade:report --downloaded --name $name");
    $p->run();
    $this->assertGreaterThan(0, $p->getExitCode(), 'Download report with no download URL does not exit with an error.');
  }


  /**
   * An upgrade report needs upgrade messages
   */
  public function testRequireUpgrademessage() {
    $name = '1776521f994630387110d2c79aa0e91e';
    $p = $this->cv("upgrade:report --upgraded --name $name");
    $p->run();
    $this->assertGreaterThan(0, $p->getExitCode(), 'Upgrade report with no messages does not exit with an error.');
  }


}
