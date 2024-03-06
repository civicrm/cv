<?php
namespace Civi\Cv\Util;

/**
 * @group std
 * @group util
 */
class AliasFilterTest extends \PHPUnit\Framework\TestCase {

  public function getExamples() {
    $exs = [];
    $exs[] = [
      ['/path/to/cv'],
      ['/path/to/cv'],
    ];
    $exs[] = [
      ['/path/to/cv', '@mysite'],
      ['/path/to/cv', '--site-alias=mysite'],
    ];
    $exs[] = [
      ['/path/to/cv', '@mysite', 'ext:dl'],
      ['/path/to/cv', '--site-alias=mysite', 'ext:dl'],
    ];
    $exs[] = [
      ['/path/to/cv', 'ext:dl', '-b', '@/path/to/file.xml', '--to=@/path/to/stuff'],
      ['/path/to/cv', 'ext:dl', '-b', '@/path/to/file.xml', '--to=@/path/to/stuff'],
    ];
    $exs[] = [
      ['/path/to/cv', '@mysite', 'ext:dl', '-b', '@/path/to/file.xml', '--to=@/path/to/stuff'],
      ['/path/to/cv', '--site-alias=mysite', 'ext:dl', '-b', '@/path/to/file.xml', '--to=@/path/to/stuff'],
    ];
    // We don't have a way to distinguish later argument-data from from aliases.
    // For now, simply require aliases go at the start.
    // $exs[] = [
    //   ['/path/to/cv', 'ext:dl', '@mysite', '-b', '@/path/to/file.xml', '--to=@/path/to/stuff'],
    //   ['/path/to/cv', 'ext:dl', '--site-alias=mysite', '-b', '@/path/to/file.xml', '--to=@/path/to/stuff'],
    // ];
    return $exs;
    // return [$exs[6]];
  }

  /**
   * @param $inputArray
   * @param $expectOutput
   * @return void
   * @dataProvider getExamples
   */
  public function testAliasFilter($inputArray, $expectOutput): void {
    // In theory, providing the full command definition might allow us to find aliases in other spots?
    // Hasn't actually worked out that way yet. But if we do, this example will  be handy.
    // $app = new Application();
    // $command = new Command('ext:dl');
    // $command->addOption('bare', 'b', InputOption::VALUE_NONE, 'Perform a basic download in a non-bootstrapped environment. Implies --level=none, --no-install, and no --refresh. You must specify the download URL.');
    // $command->addOption('to', NULL, InputOption::VALUE_OPTIONAL, 'Download to a specific directory (absolute path).');
    // $command->addArgument('key-or-name', InputArgument::IS_ARRAY, 'One or more extensions to enable. Identify the extension by full key ("org.example.foobar") or short name ("foobar"). Optionally append a URL.');
    // $app->add($command);

    $actualOutput = AliasFilter::filter($inputArray);
    $this->assertEquals($expectOutput, $actualOutput);
  }

}
