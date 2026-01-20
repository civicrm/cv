<?php
namespace Civi\Cv\Command;

/**
 * @group std
 */
class PasswordResetCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testPwReset(): void {
    if ($this->getUfType() !== 'Standalone') {
      $this->markTestSkipped('Only applies to Standalone');
    }

    $user = $this->cvOk("ev --level=full " . escapeshellarg('print($GLOBALS["_CV"]["DEMO_USER"]);'));
    $this->assertNotEmpty($user, 'Should have a demo user');

    $fullUrl = $this->cvOk("password-reset --user=" . escapeshellarg(trim($user)));
    $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_HOST));
    $this->assertNotEmpty(parse_url($fullUrl, PHP_URL_SCHEME));
    $this->assertMatchesRegularExpression(';token=[-_a-zA-Z0-9]+\.[-_a-zA-Z0-9]+\.;', $fullUrl);
  }

}
