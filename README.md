cv
==

Example: CLI
============

```bash
me@localhost$ cv show
me@localhost$ cv show --buildkit
me@localhost$ cv scr /path/to/throwaway.php
me@localhost$ cv ev 'echo Civi::paths()->get("[civicrm.root]/.");'
me@localhost$ cv url civicrm/dashboard --open
```

Example: PHP
============

Suppose you have a standalone script or a test runner which needs to execute
in the context of a CiviCRM site.  You don't want to hardcode it to a
specific path, create special-purpose config files, or require a specific
directory structure.  Instead, call `cv php:boot` and `eval()` the output:

```php
function _cv($cmd) {
  $cmd = 'cv ' . $cmd;
  $descriptorSpec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => STDERR);
  $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__);
  fclose($pipes[0]);
  $bootCode = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException("Command failed ($cmd)");
  }
  return $bootCode;
}

eval(_cv('php:boot'));
$GLOBALS['_CV'] = json_decode(_cv('show --buildkit --out=json'), 1);
printf("We should go to [%s]\n\n", json_decode(_cv('url civicrm/dashboard')));
```

Build
=====

```
php -dphar.readonly=0 `which box` build
```

Unit-Tests
==========

To run the test suite, you will need an existing CiviCRM installation,
preferrably based on buildkit. (Example: `/home/me/buildkit/build/dmaster/`)

```
$ composer create-project totten/cv
$ cd cv
$ export CV_TEST_BUILD=/home/me/buildkit/build/dmaster/
$ ./bin/phpunit
PHPUnit 3.7.10 by Sebastian Bergmann.

Configuration read from /home/me/src/cv/phpunit.xml.dist

.................................................

Time: 2 seconds, Memory: 6.50Mb

OK (49 tests, 121 assertions)
```
