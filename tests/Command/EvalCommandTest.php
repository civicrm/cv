<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;

/**
 * @group std
 * @group php
 */
class EvalCommandTest extends \Civi\Cv\CivilTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testEv() {
    $helloPhp = escapeshellarg('printf("eval says version is %s\n", CRM_Utils_System::version());');
    $p = Process::runOk($this->cv("ev $helloPhp"));
    $this->assertMatchesRegularExpression('/^eval says version is [0-9a-z\.]+\s*$/', $p->getOutput());
  }

  public function testPhpEval_ReturnObj_json() {
    $phpCode = escapeshellarg('return (object)["ab"=>"cd"];');
    $p = Process::runOk($this->cv("ev $phpCode --out=json"));
    $this->assertEquals(0, $p->getExitCode());
    $this->assertMatchesRegularExpression(';"ab":\w*"cd\";', $p->getOutput());
  }

  public function testPhpEval_ReturnObj_shell() {
    $phpCodes = [
      'return (object)["ab"=>"cd"];',
      'return new class implements JsonSerializable { public function jsonSerialize() { return ["ab" => "cd"]; } };',
    ];

    foreach ($phpCodes as $phpCode) {
      $escaped = escapeshellarg($phpCode);
      $p = Process::runOk($this->cv("ev $escaped --out=shell"));
      $this->assertEquals(0, $p->getExitCode());
      $this->assertMatchesRegularExpression(';ab=["\']cd;', $p->getOutput());
    }
  }

  public function testPhpEval() {
    $helloPhp = escapeshellarg('printf("eval says version is %s\n", CRM_Utils_System::version());');
    $p = Process::runOk($this->cv("ev $helloPhp"));
    $this->assertMatchesRegularExpression('/^eval says version is [0-9a-z\.]+\s*$/', $p->getOutput());
  }

  public function testPhpEval_Exit0() {
    $p = Process::runDebug($this->cv("ev 'exit(0);'"));
    $this->assertEquals(0, $p->getExitCode());
  }

  public function testPhpEval_Exit1() {
    $p = Process::runDebug($this->cv("ev 'exit(1);'"));
    $this->assertEquals(1, $p->getExitCode());
  }

  public function testPhpEval_ExitCodeError() {
    $p = Process::runDebug($this->cv("ev 'invalid();'"));
    $this->assertEquals(255, $p->getExitCode());
  }

  public function testPhpEval_CvVar_Full() {
    $helloPhp = escapeshellarg('printf("my admin is %s\n", $GLOBALS["_CV"]["ADMIN_USER"]);');
    $p = Process::runOk($this->cv("ev --level=full $helloPhp"));
    $this->assertMatchesRegularExpression('/^my admin is \w+\s*$/', $p->getOutput());
  }

  public function testPhpEval_CvVar_CmsFull() {
    $helloPhp = escapeshellarg('printf("my admin is %s\n", $GLOBALS["_CV"]["ADMIN_USER"]);');
    $p = Process::runOk($this->cv("ev --level=cms-full $helloPhp"));
    $this->assertMatchesRegularExpression('/^my admin is \w+\s*$/', $p->getOutput());
  }

  public function testBoot() {
    $checkBoot = escapeshellarg('echo (function_exists("drupal_add_js") || function_exists("wp_redirect") || class_exists("JFactory") || class_exists("Drupal")) ? "found" : "not-found";');

    $p1 = Process::runOk($this->cv("ev $checkBoot"));
    $this->assertMatchesRegularExpression('/^found$/', $p1->getOutput());
  }

  public function getLevels() {
    return [
      ['settings'],
      ['classloader'],
      ['full'],
      ['cms-only'],
      ['cms-full'],
    ];
  }

  /**
   * @param string $level
   * @dataProvider getLevels
   */
  public function testBootLevels($level) {
    $checkBoot = escapeshellarg('echo "Hello world";');

    $p1 = Process::runOk($this->cv("ev $checkBoot --level=$level"));
    $this->assertMatchesRegularExpression('/^Hello world/', $p1->getOutput());
  }

  public function testTestMode() {
    $checkUf = escapeshellarg('return CIVICRM_UF;');

    $p1 = Process::runOk($this->cv("ev $checkUf"));
    $this->assertMatchesRegularExpression('/(Drupal|Joomla|WordPress|Backdrop)/i', $p1->getOutput());

    $p1 = Process::runOk($this->cv("ev -t $checkUf"));
    $this->assertMatchesRegularExpression('/UnitTests/i', $p1->getOutput());
  }

  /**
   * Tests --cwd option.
   */
  public function testEvalWithCwdOption() {
    // Go somewhere else, where cv won't work.
    chdir(sys_get_temp_dir());
    $cwdOpt = "--cwd=" . escapeshellarg($this->getExampleDir());
    $helloPhp = escapeshellarg('printf("eval says version is %s\n", CRM_Utils_System::version());');
    $p = Process::runOk($this->cv("ev $cwdOpt $helloPhp"));
    $this->assertMatchesRegularExpression('/^eval says version is [0-9a-z\.]+\s*$/', $p->getOutput());
  }

  /**
   * @param string $level
   * @dataProvider getLevels
   */
  public function testUrl_cliOptions($level) {
    $checkServer = escapeshellarg('printf("HOST=%s HTTPS=%s PORT=%s\n", $_SERVER["HTTP_HOST"] ?? "", $_SERVER["HTTPS"] ?? "", $_SERVER["SERVER_PORT"]??"");');

    $expect = [
      // Current/recommended param is --url
      "ev $checkServer --url='https://u.example-a.com:4321'" => "HOST=u.example-a.com:4321 HTTPS=on PORT=4321",
      "ev $checkServer --url='http://u.example-b.com:4321'" => "HOST=u.example-b.com:4321 HTTPS= PORT=4321",
      "ev $checkServer --url='http://u.example-c.com'" => "HOST=u.example-c.com HTTPS= PORT=80",
      "ev $checkServer --url='https://u.example-d.com'" => "HOST=u.example-d.com HTTPS=on PORT=443",
      "ev $checkServer --url='u.example-e.com/subdir'" => "HOST=u.example-e.com HTTPS= PORT=80",

      // For backward compat, accept --hostname
      "ev $checkServer --hostname='https://h.example-a.com:4321'" => "HOST=h.example-a.com:4321 HTTPS=on PORT=4321",
      "ev $checkServer --hostname='h.example-b.com'" => "HOST=h.example-b.com HTTPS= PORT=80",

      // For backward compat, accept --cms-base-url
      "ev $checkServer --cms-base-url='https://c.example-a.com:4321'" => "HOST=c.example-a.com:4321 HTTPS=on PORT=4321",
      "ev $checkServer --cms-base-url='c.example-b.com'" => "HOST=c.example-b.com HTTPS= PORT=80",
    ];

    foreach ($expect as $baseCommand => $expectOutput) {
      $p1 = Process::runOk($this->cv("$baseCommand --level=$level"));
      $this->assertStringContainsString($expectOutput, $p1->getOutput());
    }
  }

  /**
   * @param string $level
   * @dataProvider getLevels
   */
  public function testUrl_envVar($level) {
    $checkServer = escapeshellarg('printf("HOST=%s HTTPS=%s PORT=%s\n", $_SERVER["HTTP_HOST"] ?? "", $_SERVER["HTTPS"] ?? "", $_SERVER["SERVER_PORT"]??"");');

    $expect = [
      // string $envVarExpr => string $expectOutput
      'HTTP_HOST=v.example-a.com:123' => "HOST=v.example-a.com:123 HTTPS= PORT=123",
      'HTTP_HOST=v.example-b.com:1234&HTTPS=on' => "HOST=v.example-b.com:1234 HTTPS=on PORT=1234",
      'HTTP_HOST=v.example-c.com' => "HOST=v.example-c.com HTTPS= PORT=80",
      'HTTP_HOST=v.example-d.com&HTTPS=on' => "HOST=v.example-d.com HTTPS=on PORT=443",
    ];

    foreach ($expect as $envVarExpr => $expectOutput) {
      parse_str($envVarExpr, $envVars);
      $p1 = Process::runOk($this->cv("ev $checkServer --level=$level")->setEnv($envVars));
      $this->assertStringContainsString($expectOutput, $p1->getOutput());
    }
  }

  /**
   * If you call 'cv' without any specific URL, then it should tend to look like CIVICRM_UF_BASEURL.
   */
  public function testUrl_default() {
    foreach (['settings', 'full'] as $level) {
      $p1 = Process::runOk($this->cv("ev 'return CIVICRM_UF_BASEURL;'"));
      $got = json_decode((string) $p1->getOutput());
      $this->assertMatchesRegularExpression(';^https?://\w+;', $got);
      $declaredUrl = parse_url($got);
      $expectParts = [];
      $expectParts[0] = 'HOST=' . $declaredUrl['host'];
      if (!empty($declaredUrl['port'])) {
        $expectParts[0] .= ':' . $declaredUrl['port'];
      }
      $expectParts[1] = 'HTTPS=' . (($declaredUrl['scheme'] ?? NULL) === 'https' ? 'on' : '');
      $expectParts[2] = 'PORT=' . $declaredUrl['port'];
      $expectOutput = implode(" ", $expectParts);

      $checkServer = escapeshellarg('printf("HOST=%s HTTPS=%s PORT=%s\n", $_SERVER["HTTP_HOST"] ?? "", $_SERVER["HTTPS"] ?? "", $_SERVER["SERVER_PORT"]??"");');
      $p2 = Process::runOk($this->cv("ev $checkServer --level=$level"));
      $this->assertStringContainsString($expectOutput, $p2->getOutput());
    }

  }

}
