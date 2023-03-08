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
  }

  public function testAuthorizedGet() {
    $body = $this->cvOk("http {$this->login} civicrm/admin");
    $this->assertRegExp(':<html:', $body);
    $this->assertRegExp(':Create and edit available tags here:', $body);
  }

  public function testUnauthorizedGet() {
    $body = $this->cvFail("http civicrm/admin");
    $this->assertRegExp(':<html:', $body);
    $this->assertNotRegExp(':Create and edit available tags here:', $body);
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
