cv
==

Requirements
============

A local CiviCRM installation

Download
========

```bash
sudo curl https://download.civicrm.org/cv/cv.phar -o /usr/local/bin/cv
sudo chmod +x /usr/local/bin/cv
```

Example: CLI
============

```bash
me@localhost$ cv show
me@localhost$ cv show --buildkit
me@localhost$ cv scr /path/to/throwaway.php
me@localhost$ cv ev 'echo Civi::paths()->get("[civicrm.root]/.");'
me@localhost$ cv url civicrm/dashboard --open
me@localhost$ cv api system.flush
```

Example: PHP
============

Suppose you have a standalone script or a test runner which needs to execute
in the context of a CiviCRM site.  You don't want to hardcode it to a
specific path, create special-purpose config files, or require a specific
directory structure.  Instead, call `cv php:boot` and `eval()` the output:

```php
/**
 * Call the "cv" command.
 *
 * @param string $cmd
 *   The rest of the command to send.
 * @param bool $raw
 *   If TRUE, return the raw output. If FALSE, parse JSON output.
 * @return string
 *   Response output (if the command executed normally).
 * @throws \RuntimeException
 *   If the command terminates abnormally.
 */
function cv($cmd, $raw = FALSE) {
  $cmd = 'cv ' . $cmd;
  $descriptorSpec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => STDERR);
  $env = $_ENV + array('CV_OUTPUT' => 'json');
  $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__, $env);
  fclose($pipes[0]);
  $bootCode = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException("Command failed ($cmd)");
  }
  return $raw ? $bootCode : json_decode($bootCode, 1);
}

eval(cv('php:boot', TRUE));
$config = cv('show --buildkit');
printf("We should go to [%s]\n\n", cv('url civicrm/dashboard'));
```

Build
=====

Use [box](http://box-project.github.io/box2/):

```
$ git clone https://github.com/civicrm/cv
$ cd cv
$ composer install
$ php -dphar.readonly=0 `which box` build
```

Unit-Tests
==========

To run the test suite, you will need an existing CiviCRM installation,
preferrably based on buildkit. (Example: `/home/me/buildkit/build/dmaster/`)

```
$ git clone https://github.com/civicrm/cv
$ cd cv
$ composer install
$ export CV_TEST_BUILD=/home/me/buildkit/build/dmaster/
$ ./bin/phpunit
PHPUnit 3.7.10 by Sebastian Bergmann.

Configuration read from /home/me/src/cv/phpunit.xml.dist

.................................................

Time: 2 seconds, Memory: 6.50Mb

OK (49 tests, 121 assertions)
```
