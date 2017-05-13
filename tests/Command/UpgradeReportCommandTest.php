<?php
namespace Civi\Cv\Command;

class UpgradeGetCommandTest extends \Civi\Cv\CivilTestCase {

  protected $revision = '4.7.123-202601040252';
  protected $reportName = '';

  public function setup() {
    $this->reportName = 'test' . md5(time() . 'test');
    parent::setup();
  }

  public function testStartReport() {
    $p = $this->cv("upgrade:report --started --revision=$this->revision --name=$this->reportName");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertTrue(isset($data['name']), 'Looking for name in output: ' . $p->getOutput());
    $this->assertTrue(isset($data['siteId']), 'Looking for siteId in output: ' . $p->getOutput());
    $this->assertTrue(is_int($data['started']), 'Looking for started timestamp in output: ' . $p->getOutput());
    $this->assertTrue(isset($data['startReport']['values'][0]['version']), 'Looking for version in start report: ' . $p->getOutput());
    $this->assertEquals(0, $p->getExitCode(), 'Start report exits with an error.');
    $response = json_decode($data['response'], TRUE);
    $this->assertContains('Saved', $response['message'], "Server not responding as saved: {$response['message']}", TRUE);
  }

  public function testSetName() {
    $downloadedTime = 1494615613;
    $p = $this->cv("upgrade:report --downloaded $downloadedTime --download-url https://download.civicrm.org/civicrm-4.7.123-drupal9.tar.gz --extracted --name $this->reportName");
    $p->run();
    $data = json_decode($p->getOutput(), 1);
    $this->assertEquals($this->reportName, $data['name'], 'Report name does not match the provided name.');
    $this->assertEquals($downloadedTime, $data['downloaded'], 'Report downloaded timestamp does not match the provided timestamp.');
    $this->assertGreaterThanOrEqual(strtotime('-5 minutes'), $data['extracted'], 'Extracted timestamp is not current time.');
    $this->assertTrue(empty($data['startReport']), 'A download/status report has `startReport` included.');
    $this->assertEquals(0, $p->getExitCode(), 'Download and extract report exits with an error.');
    $response = json_decode($data['response'], TRUE);
    $this->assertContains('Saved', $response['message'], "Server not responding as saved: {$response['message']}", TRUE);
  }


  public function testUpgraded() {
    $messageArray = array(
      'Nam adipiscing condimentum netus ac mi nunc adipiscing leo aliquet non habitant eu dignissim odio egestas mattis eu ultrices orci a mi mattis malesuada mus nisi consectetur adipiscing a. Cum netus curabitur per ut venenatis donec ante volutpat a ad a parturient nulla urna ut a dictum tortor platea posuere elementum et turpis erat condimentum ullamcorper per quam. Ac accumsan a natoque ridiculus donec cum vulputate ac sit mi sociosqu curabitur posuere velit curae.',
      'Volutpat platea venenatis ullamcorper tempor augue fusce habitant suspendisse lacus aptent ut in nibh adipiscing cubilia nunc parturient aptent a a litora cum a sem scelerisque curae quis. Scelerisque dictumst a felis eu parturient taciti platea parturient a a tristique ullamcorper eros risus condimentum a euismod scelerisque proin posuere et donec at egestas dui etiam. Adipiscing facilisi sociosqu bibendum velit etiam adipiscing ut a a vehicula a at vestibulum sem in laoreet ad varius rutrum ad lacinia commodo suspendisse arcu et malesuada. A a a quam a in massa massa turpis vivamus feugiat volutpat congue dignissim suspendisse habitasse eros torquent vestibulum adipiscing est a aptent leo urna metus facilisi faucibus nascetur. Nec tristique suscipit a habitasse ultrices suscipit inceptos metus a vel sem lacus vestibulum dolor parturient vestibulum ut malesuada sodales adipiscing molestie integer consectetur. ',
      'Vestibulum elit dictumst ac scelerisque lacinia vestibulum adipiscing consectetur dapibus purus a ante urna orci cubilia suspendisse a lorem dignissim a condimentum iaculis congue imperdiet platea interdum. Non leo vestibulum a iaculis netus a scelerisque blandit fringilla lacus suspendisse scelerisque a id litora imperdiet faucibus a torquent parturient mi egestas vehicula purus. Arcu adipiscing donec adipiscing a scelerisque est purus parturient quisque vulputate maecenas lobortis a vestibulum amet vivamus vestibulum a. Ornare a curae ut facilisis senectus leo himenaeos a ac nunc cum ullamcorper facilisi per primis vestibulum a augue. ',
      'Euismod justo fames suspendisse primis phasellus nunc a posuere scelerisque dictumst parturient dui eu conubia tempor. Laoreet hendrerit tempor massa cubilia eleifend condimentum nibh a fermentum velit ipsum a felis ridiculus dui condimentum a luctus facilisi curabitur a sodales augue erat mus. Diam cum penatibus scelerisque est dapibus a suspendisse a venenatis condimentum ullamcorper eleifend pulvinar id. Euismod ad aenean ipsum nisl aliquam est a litora a sapien vestibulum imperdiet parturient habitasse cras duis fusce netus erat vestibulum facilisis posuere lacus a morbi gravida a adipiscing. Feugiat adipiscing est dis a ut varius est habitasse convallis per hac scelerisque habitant lobortis sem est sapien suspendisse cursus.',
    );
    $messages = json_encode($messageArray);
    $p = $this->cv("upgrade:report --upgraded --upgrade-messages=php://stdin --name $this->reportName");
    $p->setInput(json_encode($messageArray));
    $p->run();
    $data = json_decode($p->getOutput(), TRUE);
    $this->assertEquals($messageArray, json_decode($data['upgradeReport'], TRUE), 'Report data does not match the provided data.');
    $this->assertEquals(0, $p->getExitCode(), 'Upgrade report exits with an error.');
    $response = json_decode($data['response'], TRUE);
    $this->assertContains('Saved', $response['message'], "Server not responding as saved: {$response['message']}", TRUE);

    // Try second time
    $p->run();
    $data = json_decode($p->getOutput(), TRUE);
    $response = json_decode($data['response'], TRUE);
    $this->assertContains('already been set', $response['message'], "Server not responding as saved: {$response['message']}", TRUE);
  }

  public function testFinished() {
    $p = $this->cv("upgrade:report --finished --name $this->reportName");
    $p->run();
    $data = json_decode($p->getOutput(), TRUE);
    $response = json_decode($data['response'], TRUE);
    $this->assertContains('Saved', $response['message'], "Server not responding as saved: {$response['message']}", TRUE);
    $this->assertEquals(0, $p->getExitCode(), 'Finished report exits with an error.');
  }

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
    $p = $this->cv("upgrade:report --downloaded --name $this->reportName");
    $p->run();
    $this->assertGreaterThan(0, $p->getExitCode(), 'Download report with no download URL does not exit with an error.');
  }


  /**
   * An upgrade report needs upgrade messages
   */
  public function testRequireUpgrademessage() {
    $p = $this->cv("upgrade:report --upgraded --name $this->reportName");
    $p->run();
    $this->assertGreaterThan(0, $p->getExitCode(), 'Upgrade report with no messages does not exit with an error.');
  }

}
