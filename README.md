cv
==

The `cv` command is a utility for interacting with a CiviCRM installation. It performs an automatic scan to locate and boot the CiviCRM installation. It provides command-line access to helper functions and configuration data, such as APIv3 and site URLs.

Requirements
============

* PHP v7.3+.
* A local CiviCRM installation.
* Systems with special file-layouts may need to [configure bootstrap](#bootstrap).

Download
========

`cv` is distributed in PHAR format, which is a portable executable file (for PHP). It should run on most Unix-like systems where PHP 5.4+ is installed.

Simply download [`cv`](https://download.civicrm.org/cv/cv.phar) and put it somewhere in the PATH, eg

```bash
sudo curl -LsS https://download.civicrm.org/cv/cv.phar -o /usr/local/bin/cv
sudo chmod +x /usr/local/bin/cv
```

> __Need PHP 5.3?__: The last version to support PHP v5.3 was [cv v0.1.32](https://download.civicrm.org/cv/cv.phar-2018-01-11-8dd41af7).
> Please note that the current version of `civicrm-core` no longer supports PHP v5.3.

Documentation
=============

`cv` provides a number of subcommands. To see a list, run `cv` without any arguments.

For detailed help about a specific subcommand, use `-h` as in `cv api -h`.

There are some general conventions:
 * Many subcommands support common bootstrap options, such as `--user`,
   `--level`, and `--test`.
 * Many subcommands support multiple output formats using `--out`. You may
   set a general preference with an environment variable, e.g.
   `export CV_OUTPUT=json-pretty` or `export CV_OUTPUT=php`.

Example: CLI
============

```bash
me@localhost$ cd /var/www/my/web/site
me@localhost$ cv vars:show
me@localhost$ cv scr /path/to/throwaway.php
me@localhost$ cv ev 'echo Civi::paths()->getPath("[civicrm.root]/.");'
me@localhost$ cv ev 'echo Civi::paths()->getUrl("[civicrm.root]/.");'
me@localhost$ cv url civicrm/dashboard --open
me@localhost$ cv api contact.get last_name=Smith
me@localhost$ cv dl cividiscount
me@localhost$ cv en cividiscount
me@localhost$ cv dis cividiscount
me@localhost$ cv debug:container
me@localhost$ cv debug:event-dispatcher
me@localhost$ cv flush
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
  $env = (!empty($_ENV) ? $_ENV : getenv()) + array('CV_OUTPUT' => 'json');
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

eval(cv('php:boot', 'phpcode'));
$config = cv('vars:show');
printf("We should navigate to the dashboard: %s\n\n", cv('url civicrm/dashboard'));
```

Example: NodeJS
===============

See https://github.com/civicrm/cv-nodejs

Bootstrap
=========

`cv` must find and bootstrap the local instance of CiviCRM, Drupal, WordPress, or similar.  This may work a few ways:

* __Automatic__: By default, `cv` checks the current directory and each parent directory for evidence of well-known environment (such as Drupal or WordPress).

    The automatic search is designed to work with a default site-layout -- as seen in a typical "zip" or "tar" file
    from `drupal.org`, `wordpress.org`, or similar.  Some deployments add more advanced options -- such as
    configuring "multi-site", adding bespoke "symlinks", or moving the `wp-admin` folder.  For advanced layouts, you
    may need to set an environment variable.

* __`CIVICRM_BOOT`__: To enable the _standard boot protocol_, set this environment variable. Specify the CMS type and base-directory. Examples:

    ```bash
    export CIVICRM_BOOT="Drupal://var/www/public"
    export CIVICRM_BOOT="Drupal8://admin@/var/www/public"
    export CIVICRM_BOOT="WordPress:/$HOME/sites/my-wp-site/web/"
    export CIVICRM_BOOT="Auto://."
    ```

    (Note: In the standard protocol, `cv` loads a CMS first and then asks it to bootstrap CiviCRM. This is more representative of
    a typical HTTP page-view, and it is compatible with commands like `core:install`. However, it has not been used for as long.)

* __`CIVICRM_SETTINGS`__: To enable the _legacy boot protocol_, set this environment variable. Specify the `civicrm.settings.php` location. Examples:

    ```bash
    export CIVICRM_SETTINGS="/var/www/sites/default/files/civicrm.settings.php"
    export CIVICRM_SETTINGS="Auto"
    ```

    (Note: In the legacy protocol, `cv` loads CiviCRM and then asks CiviCRM to boostrap the CMS.  However, it is
    less representative of a typical HTTP page-view, and it is incompatible with commands like `core:install`. You might use it
    for headless testing or as fallback/work-around if any bugs are discovered in the standard protocol.)

> ___NOTE___: In absence of a configuration variable, the __Automatic__ mode specifically behaves like `CIVICRM_BOOT="Auto://."` (in v0.4.x
> and later).  This is a change from v0.3.x, where the default behaved like `CIVICRM_SETTINGS="Auto"`.

Build
=====

To build a new `phar` executable, use [box](http://box-project.github.io/box2/):

```
$ git clone https://github.com/civicrm/cv
$ cd cv
$ composer install
$ php -dphar.readonly=0 `which box` build
```

If you want to run with the same versions of PHP+box that are used for official builds, then run:

```
nix-shell --run ./build.sh
```

Unit-Tests (Standard)
=====================

To run the standard test suite, you will need to pick an existing CiviCRM
installation and put it in `CV_TEST_BUILD`, as in:

```
$ git clone https://github.com/civicrm/cv
$ cd cv
$ composer install
$ export CV_TEST_BUILD=/home/me/buildkit/build/dmaster/web/
$ phpunit7 --group std
PHPUnit 7.5.15 by Sebastian Bergmann and contributors.

...............................................................  63 / 118 ( 53%)
.......................................................         118 / 118 (100%)

Time: 3.13 minutes, Memory: 14.00 MB

OK (118 tests, 295 assertions)
```

> We generally choose an existing installation based on `civibuild`
> configuration like `dmaster`. The above example assumes that your
> build is located at `/home/me/buildkit/build/dmaster/`.


To be quite thorough, you may want to test against multiple builds (e.g.
with various CMS's and file structures).  Prepare these builds separately
and loop through them, e.g.

```
$ for CV_TEST_BUILD in /home/me/buildkit/build/{dmaster,wpmaster,bmaster} ; do export CV_TEST_BUILD; phpunit7 --group std; done
```

Unit-Tests (Installer)
======================

The `cv core:install` and `cv core:uninstall` commands have more stringent execution requirements, e.g.

* Each test-run needs to work with an empty build (which does not have a Civi database or settings file).
  It specifically calls `civibuild` and `amp` to create+destroy builds during execution.
* These commands, in turn, may add new vhosts and databases. This can require elevated privileges (`sudo`).
* These commands have more potential failure points (e.g. intermittent networking issues can disrupt
  the test). To monitor them, you should set `DEBUG=1`.
* There must be a copy of the `civicrm-setup` source tree.  At time of writing, this is not yet bundled with
  the main tarballs, but you can set `CV_SETUP_PATH` to point to your own copy.

Given these extra requirements, this test runs as a separate group.

A typical execution might look like:

```
$ env DEBUG=1 OFFLINE=1 CV_SETUP_PATH=$HOME/src/civicrm-setup phpunit7 --group installer
```
