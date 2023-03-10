<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 */
class HttpCommandTest extends \Civi\Cv\CivilTestCase {

  /**
   * CLI params to specify the login user for these tests.
   *
   * Ex: '--login --user=admin' or '-LU admin'
   *
   * @var string
   */
  protected $login = '-LU admin';

  public function setUp(): void {
    parent::setUp();
    $this->cvOk('en authx');
  }

  public function testAuthorizedGet() {
    $body = $this->cvOk("http {$this->login} civicrm/authx/id");
    $data = json_decode($body, TRUE);
    $this->assertTrue(is_numeric($data['contact_id']), "civicrm/authx/id should return current contact. Received: $body");

    $body = $this->cvOk("http {$this->login} 'civicrm/user?reset=1'");
    $this->assertRegExp(':<html:', $body);
    $this->assertRegExp(';Your Group;', $body);
  }

  public function testUnauthorizedGet() {
    $body = $this->cvOk("http civicrm/authx/id");
    $data = json_decode($body, TRUE);
    $this->assertTrue(empty($data['contact_id']), "civicrm/authx/id should return anonymous. Received: $body");

    $body = $this->cvFail("http 'civicrm/user?reset=1'");
    $this->assertRegExp(':<html:', $body);
    $this->assertNotRegExp(';Your Group;', $body);
  }

  public function testGetWebService() {
    $data = escapeshellarg('params=' . urlencode(json_encode(['limit' => 1])));
    $body = $this->cvOk("http {$this->login} civicrm/ajax/api4/Group/get --data $data");
    $parsed = json_decode($body, TRUE);
    $this->assertTrue(!empty($parsed['values'][0]['title']) && is_string($parsed['values'][0]['title']),
      'Response should include a title. Received: ' . $body);
    $this->assertEquals(1, count($parsed['values']), "Response should have been limited to 1 record.");
  }

  public function testGetWebServiceVerbose() {
    $data = escapeshellarg('params=' . urlencode(json_encode(['limit' => 1])));
    $p = Process::runOk($this->cv("http -v {$this->login} civicrm/ajax/api4/Group/get --data $data"));

    $parsed = json_decode($p->getOutput(), TRUE);
    $this->assertTrue(!empty($parsed['values'][0]['title']) && is_string($parsed['values'][0]['title']),
      'Response should include a title. Received: ' . $p->getOutput());
    $this->assertEquals(1, count($parsed['values']), "Response should have been limited to 1 record.");

    $error = $p->getErrorOutput();
    $this->assertRegExp(';> POST http;', $error);
    $this->assertRegExp(';> Content-Type: application/x-www-form-urlencoded;', $error);
    $this->assertRegExp(';> X-Civi-Auth: Bearer;', $error);
    $this->assertRegExp(';< Content-Type: application/json;', $error);
  }

}
