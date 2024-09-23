<?php
namespace Civi\Cv\Util;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UrlCommandTrait
 * @package Civi\Cv\Util
 */
trait UrlCommandTrait {

  /**
   * @var int
   */
  protected $defaultJwtTimeout = 300;

  /**
   * @return $this
   */
  protected function configureUrlOptions() {
    $this
      ->addArgument('path', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Relative path to a CiviCRM page, such as "civicrm/contact/view?reset=1&cid=1"')
      ->addOption('relative', 'r', InputOption::VALUE_NONE, 'Prefer relative URL format. (Default: absolute)')
      ->addOption('frontend', 'f', InputOption::VALUE_NONE, 'Equivalent to --entry=frontend')
      ->addOption('entry', NULL, InputOption::VALUE_REQUIRED, 'Request frontend or backend style URLs', 'default')
      ->addOption('login', 'L', InputOption::VALUE_NONE, 'Add an authentication code (based on current user; uses authx; expires=' . $this->defaultJwtTimeout . 's)')
      ->addOption('ext', 'x', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'An extension name. Identify the extension by full key ("org.example.foobar") or short name ("foobar"). Use "." for the default extension dir.')
      ->addOption('config', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A config property. (Ex: "templateCompileDir/en_US")')
      ->addOption('dynamic', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A dynamic path expression (v4.7+) (Ex: "[civicrm.root]/packages")');

    return $this;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param bool $useDefault
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function createUrls(InputInterface $input, OutputInterface $output, bool $useDefault = TRUE): array {
    $rows = [];
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
      if ($useDefault) {
        $rows[] = $this->resolveRoute('', $input);
      }
      else {
        throw new \RuntimeException("No paths specified");
      }
    }
    foreach (array_keys($rows) as $rowId) {
      $rows[$rowId]['value'] = $this->normalizeUrl($rows[$rowId]['value']);
    }

    if ($input->getOption('login')) {
      if (!\CRM_Extension_System::singleton()->getMapper()->isActiveModule('authx')) {
        if (\Civi\Cv\Cv::io()->confirm('Enable authx?')) {
          // ^^ Does the question go to STDERR or STDOUT?
          $output->getErrorOutput()->writeln('<info>Enabling extension "authx"</info>');
          civicrm_api3('Extension', 'enable', ['key' => 'authx']);
        }
        else {
          throw new \RuntimeException('Missing required extension: authx');
        }
      }

      $authxFlow = $input->hasOption('authx-flow') ? $input->getOption('authx-flow') : 'login';

      $cid = \CRM_Core_Session::getLoggedInContactID();
      if (!$cid) {
        throw new \RuntimeException('The "--login" option requires specifying an active user/contact ("--user=X").');
      }
      $token = \Civi::service('crypto.jwt')->encode([
        'exp' => time() + $this->defaultJwtTimeout,
        'sub' => 'cid:' . $cid,
        'scope' => 'authx',
      ]);
      $rows = array_map(
        function ($row) use ($token, $authxFlow) {
          switch ($authxFlow) {
            case 'login':
              $delim = strpos($row['value'], '?') === FALSE ? '?' : '&';
              $row['value'] .= $delim . '_authxSes=1&_authx=Bearer+' . urlencode($token);
              break;

            case 'param':
              $delim = strpos($row['value'], '?') === FALSE ? '?' : '&';
              $row['value'] .= $delim . '_authx=Bearer+' . urlencode($token);
              break;

            case 'xheader':
              $row['headers']['X-Civi-Auth'] = 'Bearer ' . $token;
              break;

            case 'header':
              $row['headers']['Authorization'] = 'Bearer ' . $token;
              break;
          }
          return $row;
        },
        $rows
      );
    }
    return $rows;
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

    if (strpos($extExpr, '/')) {
      [$keyOrName, $file] = explode('/', $extExpr, 2);
    }
    else {
      [$keyOrName, $file] = [$extExpr, NULL];
    }
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
    [$configProperty, $file] = explode('/', $configExpr, 2);
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
    $entry = $input->getOption('frontend') ? 'frontend' : $input->getOption('entry');

    return [
      'type' => 'router',
      'expr' => $pathExpr,
      'value' => \CRM_Utils_System::url(
        $path,
        $query,
        !$input->getOption('relative'),
        $fragment,
        FALSE,
        ($entry === 'frontend'),
        ($entry === 'backend')
      ),
    ];
  }

  protected function normalizeUrl(string $url): string {
    // Across different versions+environments+APIs, we don't get consistent renditions of relative/absolute.
    $preferRelative = \Civi\Cv\Cv::input()->getOption('relative');
    if (!$preferRelative && $url[0] === '/') {
      // Base of the domain. Drop paths. Keep scheme+host+port.
      $baseUrl = preg_replace(';^(https?://[^/]+/?).*$;', '\1', \CRM_Utils_System::baseCMSURL());
      return $this->urlJoin($baseUrl, ltrim($url, '/'));
    }
    if ($preferRelative && preg_match(';^(https?://[^/]+)(/.*)$;', $url, $m)) {
      return $m[2];
    }
    return $url;
  }

}
