<?php
namespace Civi\Cv\Util;

/**
 * @group std
 * @group util
 */
class SimulateWebTest extends \PHPUnit\Framework\TestCase {

  private array $originalServer;

  protected function setUp(): void {
    parent::setUp();
    $this->originalServer = $_SERVER;
    unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_PORT'], $_SERVER['SERVER_PORT'], $_SERVER['HTTPS']);
  }

  protected function tearDown(): void {
    $_SERVER = $this->originalServer;
    parent::tearDown();
  }

  public function dataDetectEnvUrl(): array {
    return [
      'host only' => [['HTTP_HOST' => 'example.org'], 'http://example.org'],
      'host and port' => [['HTTP_HOST' => 'example.org', 'HTTP_PORT' => '8443'], 'http://example.org:8443'],
      'HTTPS=on' => [['HTTP_HOST' => 'example.org', 'HTTPS' => 'on'], 'https://example.org'],
      // Environment variables are strings, so SERVER_PORT=443 from a shell arrives as "443".
      'SERVER_PORT string' => [['HTTP_HOST' => 'example.org', 'SERVER_PORT' => '443'], 'https://example.org'],
      'SERVER_PORT int' => [['HTTP_HOST' => 'example.org', 'SERVER_PORT' => 443], 'https://example.org'],
      'no host' => [[], NULL],
    ];
  }

  /**
   * @dataProvider dataDetectEnvUrl
   */
  public function testDetectEnvUrl(array $server, ?string $expected): void {
    $_SERVER = array_merge($_SERVER, $server);
    $this->assertSame($expected, SimulateWeb::detectEnvUrl());
  }

}
