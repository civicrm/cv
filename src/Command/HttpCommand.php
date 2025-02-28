<?php

namespace Civi\Cv\Command;

use Civi\Cv\Cv;
use Civi\Cv\Util\ExtensionTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Civi\Cv\Util\UrlCommandTrait;
use GuzzleHttp\Client;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HttpCommand extends CvCommand {

  use ExtensionTrait;
  use StructuredOutputTrait;
  use UrlCommandTrait;

  protected function configure() {
    $this
      ->setName('http')
      ->setDescription('Send an HTTP request')
      ->configureUrlOptions()
      ->addOption('request', 'X', InputOption::VALUE_REQUIRED, 'HTTP Request Method (GET, POST, etc)')
      ->addOption('data', 'D', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'HTTP Request Data')
      ->addOption('header', 'H', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'HTTP Request Header')
      ->addOption('authx-flow', NULL, InputOption::VALUE_REQUIRED, 'How to present authentication credentials [header, login, param, xheader]', 'xheader')
      ->setHelp('
Request a page or resource via HTTP.

Examples:
  cv http get civicrm/dashboard
  cv http -x get cividiscount/css/example.css
  cv http post -d \'[civicrm.root]/extern/ipn.php\'
  cv http get -d \'[civicrm.files]/foo.txt\'

NOTE: For a longer list of example URLs, see `cv url --help`

NOTE: Some defaults change depending the presence of --data:
      Without --data, the default verb is GET.
      With --data, the default verb is POST, and the default `Content-Type`
      is `application/x-www-form-urlencoded`

NOTE: If you use `--login` and do not have `authx`, then it prompts about
      enabling the extension. The extra I/O may influence some scripted
      use-cases.
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $method = $input->getOption('request');
    $data = $this->parseRequestData($input);
    $headers = $this->parseRequestHeaders($input);

    if ($data) {
      // TODO: Headers should be case-insensitive.
      $headers['Content-Length'] = strlen($data);
      $headers['Content-Type'] = ($headers['Content-Type'] ?? 'application/x-www-form-urlencoded');
    }
    if (empty($method)) {
      $method = ($data === NULL) ? 'GET' : 'POST';
    }

    $rows = $this->createUrls($input, $output, FALSE);
    foreach ($rows as $row) {
      $statusCode = $this->sendRequest($output, $method, $row['value'], array_merge($headers, $row['headers'] ?? []), $data);
      return ($statusCode >= 200 && $statusCode < 300) ? 0 : $statusCode;
    }
    return 0;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @param string $method
   * @param string $url
   * @param array $headers
   * @param string|null $body
   * @return int
   *   Response code
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function sendRequest(OutputInterface $output, string $method, string $url, array $headers = [], ?string $body = NULL): int {
    $method = strtoupper($method);
    $verbose = function(string $text) {
      Cv::errorOutput()->writeln($text, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_VERBOSE);
    };

    $verbose("> $method $url");
    foreach ($headers as $name => $value) {
      $verbose("> $name: $value");
    }

    $c = new Client();
    $response = $c->request($method, $url, [
      'http_errors' => FALSE,
      'headers' => $headers,
      'body' => $body,
    ]);

    $verbose('< HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
    foreach ($response->getHeaders() as $name => $values) {
      foreach ($values as $value) {
        $verbose("< $name: $value");
      }
    }

    $body = $response->getBody();
    while (!$body->eof()) {
      $output->write($body->read(16 * 1024));
    }

    return $response->getStatusCode();
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return string|null
   */
  protected function parseRequestData(InputInterface $input): ?string {
    $inputData = $input->getOption('data');
    $data = NULL;
    if (!empty($inputData)) {
      foreach ($inputData as $datum) {
        $data = is_null($data) ? '' : "$data&";

        if ($datum === '-') {
          $data .= file_get_contents('php://stdin');
        }
        elseif ($datum[0] === '@') {
          $file = substr($datum, 1);
          if (!file_exists($file) || !is_readable($file)) {
            throw new \RuntimeException("Cannot read file: $file");
          }
          $data .= file_get_contents($file);
        }
        else {
          $data .= $datum;
        }
      }
    }
    return $data;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @return array
   */
  protected function parseRequestHeaders(InputInterface $input): array {
    $headers = [];
    foreach ($input->getOption('header') as $str) {
      [$key, $value] = explode(':', $str, 2);
      $headers[$key] = ltrim($value, ' ');
    }
    return $headers;
  }

}
