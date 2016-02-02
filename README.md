Requirements
============

A local CiviCRM installation.

Support may vary depending on host environment (CMS type, file-structure, etc).
 * *Tested heavily*: Drupal 7 single-site, WordPress single-site, UnitTests
 * *Tested lightly*: Backdrop single-site, WordPress (alternate content root)
 * *Untested*: Drupal 7 multi-site, WordPress multi-site, Joomla, Drupal 6, Drupal 8; any heavy symlinking

Download
========

```bash
sudo curl -LsS https://download.civicrm.org/cv/cv.phar -o /usr/local/bin/cv
sudo chmod +x /usr/local/bin/cv
```

Example: CLI
============

```bash
me@localhost$ cd /var/www/my/web/site
me@localhost$ cv vars:show
me@localhost$ cv scr /path/to/throwaway.php
me@localhost$ cv ev 'echo Civi::paths()->get("[civicrm.root]/.");'
me@localhost$ cv url civicrm/dashboard --open
me@localhost$ cv api system.flush
```

If you intend to run unit-tests, and if you do *not* use `civibuild`,
then you may need to supply some additional site information (such as
the name of the test users). To do this, run:

```bash
me@localhost$ cd /var/www/my/web/site
me@localhost$ cv vars:fill
me@localhost$ vi ~/.cv.json
```


Example: PHP
============

Suppose you have a standalone script or a test runner which needs to execute
in the context of a CiviCRM site.  You don't want to hardcode it to a
specific path, create special-purpose config files, or require a specific
directory structure.  Instead, call `cv php:boot` and `eval()`. The simplest way:

```php
eval(`cv php:boot`)
```

However, it is better to create a small wrapper function to improve error-handling
and output parsing:

```php
/**
 * Call the "cv" command.
 *
 * @param string $cmd
 *   The rest of the command to send.
 * @param string $decode
 *   Ex: 'json' or 'phpcode'.
 * @return string
 *   Response output (if the command executed normally).
 * @throws \RuntimeException
 *   If the command terminates abnormally.
 */
function cv($cmd, $decode = 'json') {
  $cmd = 'cv ' . $cmd;
  $descriptorSpec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => STDERR);
  $env = $_ENV + array('CV_OUTPUT' => 'json');
  $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__, $env);
  fclose($pipes[0]);
  $result = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  if (proc_close($process) !== 0) {
    throw new RuntimeException("Command failed ($cmd):\n$result");
  }
  switch ($decode) {
    case 'raw':
      return $result;

    case 'phpcode':
      // If the last output is /*PHPCODE*/, then we managed to complete execution.
      if (substr(trim($result), 0, 12) !== "/*BEGINPHP*/" || substr(trim($result), -10) !== "/*ENDPHP*/") {
        throw new \RuntimeException("Command failed ($cmd):\n$result");
      }
      return $result;

    case 'json':
      return json_decode($result, 1);

    default:
      throw new RuntimeException("Bad decoder format ($decode)");
  }
}

eval(cv('php:boot', 'phpcode));
$config = cv('show --buildkit');
printf("We should go to [%s]\n\n", cv('url civicrm/dashboard'));
```

Build
=====

To bild a new `phar` executable, use [box](http://box-project.github.io/box2/):

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
