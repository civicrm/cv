<?php
namespace Civi\Cv\Command;

use Civi\Cv\Application;
use Civi\Cv\Encoder;
use Civi\Cv\Util\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class UrlCommand extends BaseExtensionCommand {

  protected function configure() {
    $this
      ->setName('url')
      ->setDescription('Compose a URL to a CiviCRM page')
      ->addArgument('path', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Relative path to a CiviCRM page, such as "civicrm/contact/view?reset=1&cid=1"')
      ->addOption('out', NULL, InputArgument::OPTIONAL, 'Specify return format (' . implode(',', Encoder::getTabularFormats()) . ')', Encoder::getDefaultFormat())
      ->addOption('columns', NULL, InputOption::VALUE_REQUIRED, 'List of columns to display (comma separated; type, expr, value)')
      ->addOption('relative', 'r', InputOption::VALUE_NONE, 'Prefer relative URL format. (Default: absolute)')
      ->addOption('frontend', 'f', InputOption::VALUE_NONE, 'Generate a frontend URL (Default: backend)')
      ->addOption('open', 'O', InputOption::VALUE_NONE, 'Open a local web browser')
      ->addOption('ext', 'x', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'An extension name. Identify the extension by full key ("org.example.foobar") or short name ("foobar"). Use "." for the default extension dir.')
      ->addOption('config', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A config property. (Ex: "templateCompileDir/en_US")')
      ->addOption('dynamic', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A dynamic path expression (v4.7+) (Ex: "[civicrm.root]/packages")')
      // The original contract only displayed one URL. We subsequently added support for list/csv/table output which require multi-record orientation.
      // It's ambiguous whether JSON/serialize formats should stick to the old output or multi-record output.
      ->addOption('tabular', NULL, InputOption::VALUE_NONE, 'Force display in multi-record mode. (Enabled by default for list,csv,table formats.)')
      ->setHelp('
Compose a URL to a CiviCRM page or resource

Examples: Lookup the site root
  cv url

Examples: Lookup URLs with the standard router
  cv url civicrm/dashboard
  cv url civicrm/dashboard --open
  cv url \'civicrm/a/#/mailing/123?angularDebug=1\'

Examples: Lookup URLs for extension resources
  cv url -x org.civicrm.module.cividiscount
  cv url -x cividiscount
  cv url -x cividiscount/css/example.css

Examples: Lookup URLs using configuration properties
  cv url -c imageUploadURL
  cv url -c imageUploadURL/example.png

Examples: Lookup URLs using dynamic expressions
  cv url -d \'[civicrm.root]/extern/ipn.php\'
  cv url -d \'[civicrm.files]\'
  cv url -d \'[cms.root]/index.php\'

Examples: Lookup multiple URLs
  cv url -x cividiscount -x volunteer civicrm/admin --out=table
  cv url -x cividiscount -x volunteer civicrm/admin --out=json --tabular

NOTE: To change the default output format, set CV_OUTPUT.
');
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if (in_array($input->getOption('out'), Encoder::getTabularFormats())
    && !in_array($input->getOption('out'), Encoder::getFormats())) {
      $input->setOption('tabular', TRUE);
    }

    $this->boot($input, $output);

    $rows = array();
    if ($input->getOption('ext')) {
      foreach ($input->getOption('ext') as $extExpr) {
        $rows[] = $this->resolveExt($extExpr, $output);
      }
    }
    if ($input->getOption('dynamic')) {
      foreach ($input->getOption('dynamic') as $dynExpr) {
        $rows[] = $this->resolveDynamic($dynExpr, $output);
      }
    }
    if ($input->getOption('config')) {
      foreach ($input->getOption('config') as $configExpr) {
        $rows[] = $this->resolveConfig($configExpr, $output);
      }
    }
    if ($input->getArgument('path')) {
      foreach ($input->getArgument('path') as $pathExpr) {
        $rows[] = $this->resolveRoute($pathExpr, $input);
      }
    }
    if (count($rows) === 0) {
      $rows[] = $this->resolveRoute('', $input);
    }

    if ($input->getOption('open')) {
      $cmd = $this->pickCommand();
      if (!$cmd) {
        throw new \RuntimeException("Failed to locate 'xdg-open' or 'open'. Open not supported on this system.");
      }
      foreach ($rows as $row) {
        if (!empty($row['value'])) {
          $escaped = escapeshellarg($row['value']);
          Process::runOk(new \Symfony\Component\Process\Process("$cmd $escaped"));
        }
      }
    }

    if ($input->getOption('tabular')) {
      $columns = $this->parseColumns($input, array(
        'list' => array('value'),
      ));
      $this->sendTable($input, $output, $rows, $columns);
    }
    else {
      if (count($rows) !== 1) {
        $output->getErrorOutput()->writeln('<error>Detected multiple URLs. You must specify --tabular.</error>');
        return 1;
      }
      else {
        $this->sendResult($input, $output, $rows[0]['value']);
      }
    }

    return (in_array(NULL, $rows)) ? 1 : 0;
  }

  protected function pickCommand($commands = array('xdg-open', 'open')) {
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    foreach ($commands as $cmd) {
      foreach ($paths as $path) {
        $file = $path . DIRECTORY_SEPARATOR . $cmd;
        if (is_file($file)) {
          return $cmd;
        }
      }
    }
    return NULL;
  }

  /**
   * Resolve the "--ext/-x" parameter to a URL.
   *
   * @param string $extExpr
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return array|null
   *   - type: string
   *   - expr: string
   *   - value: string
   */
  protected function resolveExt($extExpr, OutputInterface $output) {
    $mapper = \CRM_Extension_System::singleton()->getMapper();

    list ($keyOrName, $file) = explode('/', $extExpr, 2);
    if ($keyOrName === '.') {
      return array(
        'type' => 'ext',
        'expr' => $extExpr,
        'value' => $this->urlJoin(\CRM_Core_Config::singleton()->extensionsURL, $file),
      );
    }

    if (strpos($keyOrName, '.') === FALSE) {
      $shortMap = $this->getShortMap();
      if (isset($shortMap[$keyOrName]) && count($shortMap[$keyOrName]) === 1) {
        $keyOrName = $shortMap[$keyOrName][0];
      }
    }

    try {
      return array(
        'type' => 'ext',
        'expr' => $extExpr,
        'value' => $this->urlJoin($mapper->keyToUrl($keyOrName), $file),
      );
    }
    catch (\CRM_Extension_Exception_MissingException $e) {
      $output->getErrorOutput()
        ->writeln("<error>Ignoring unrecognized extension \"$keyOrName\"</error>");
      // $returnValue = 1;
      return NULL;
    }
  }

  /**
   * Resolve the "--dynamic/-d" parameter to a URL.
   *
   * @param string $dynExpr
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return array|null
   *   - type: string
   *   - expr: string
   *   - value: string
   */
  protected function resolveDynamic($dynExpr, OutputInterface $output) {
    if (!is_callable(array('Civi', 'paths'))) {
      $output->getErrorOutput()
        ->writeln("<error>Dynamic path expressions are only available on CiviCRM v4.7+</error>");
      return NULL;
    }

    if (preg_match(';^(\[[^\]]+\])([\\/]?)$;', $dynExpr, $matches)) {
      // getPath() is wonky about "[civicrm.root]" or "[civicrm.root]/",
      // so we have to trick it.
      $dyn = \Civi::paths()->getUrl($matches[1] . "/./", 'absolute');
      $value = preg_replace(';/./$;', '', $dyn) . $matches[2];
      return array(
        'type' => 'dynamic',
        'expr' => $dynExpr,
        'value' => $value,
      );
    }
    else {
      // Phew, we can do a normal lookup.
      return array(
        'type' => 'dynamic',
        'expr' => $dynExpr,
        'value' => \Civi::paths()->getUrl($dynExpr, 'absolute'),
      );
    }
  }

  /**
   * Resolve the "--config/-c" parameter to a URL.
   *
   * @param string $configExpr
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return array|null
   *   - type: string
   *   - expr: string
   *   - value: string
   */
  protected function resolveConfig($configExpr, OutputInterface $output) {
    list ($configProperty, $file) = explode('/', $configExpr, 2);
    $dir = \CRM_Core_Config::singleton()->{$configProperty};
    return array(
      'type' => 'config',
      'expr' => $configExpr,
      'value' => $this->urlJoin($dir, $file),
    );
  }

  protected function urlJoin($folder, $file) {
    if ($folder == NULL || $folder == FALSE) {
      return $folder;
    }
    if ($file !== NULL && $file !== FALSE) {
      return \CRM_Utils_File::addTrailingSlash($folder, '/') . $file;
    }
    else {
      return rtrim($folder, DIRECTORY_SEPARATOR);
    }
  }

  /**
   * @param string $pathExpr
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   */
  protected function resolveRoute($pathExpr, InputInterface $input) {
    $path = parse_url($pathExpr, PHP_URL_PATH);
    $query = parse_url($pathExpr, PHP_URL_QUERY);
    $fragment = parse_url($pathExpr, PHP_URL_FRAGMENT);

    return array(
      'type' => 'router',
      'expr' => $pathExpr,
      'value' => \CRM_Utils_System::url(
        $path,
        $query,
        !$input->getOption('relative'),
        $fragment,
        FALSE,
        (bool) $input->getOption('frontend'),
        (bool) !$input->getOption('frontend')
      ),
    );
  }

}
