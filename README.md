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

`cv` is distributed in PHAR format, which is a portable executable file (for PHP). It should run on most Unix-like systems where PHP is installed.
Here are two quick ways to download it:

* Download [the latest release of `cv.phar`](https://download.civicrm.org/cv/cv.phar) (*[SHA256](https://download.civicrm.org/cv/cv.SHA256SUMS),
  [GPG](https://download.civicrm.org/cv/cv.phar.asc)*) and put it in the PATH. For example:

    ```bash
    sudo curl -LsS https://download.civicrm.org/cv/cv.phar -o /usr/local/bin/cv
    sudo chmod +x /usr/local/bin/cv
    ```

    (*Learn more: [Install `cv.phar` as system-wide tool (Linux/BSD/macOS)](#phar-unix)*)

* Use [phar.io's `phive` installer](https://phar.io/) to download, validate, and cache the `cv.phar` file.

    ```bash
    phive install civicrm/cv
    ```

    (*Learn more: [Install `cv.phar` as project tool (phive)](#phar-phive)*)

There are several more options for downloading `cv`. See also:

* [Download URLs](doc/download.md#urls)
* [Comparison](doc/download.md#comparison)
* [Install `cv.phar` as system-wide tool (Linux/BSD/macOS)](doc/download.md#phar-unix)
* [Install `cv.phar` as project tool (composer)](doc/download.md#phar-composer)
* [Install `cv.phar` as project tool (phive)](doc/download.md#phar-phive)
* [Install `cv.git` as system-wide tool (Linux/BSD/macOS)](doc/download.md#src-unix)
* [Install `cv.git` as system-wide tool (Windows)](doc/download.md#src-win)
* [Install `cv.git` as project tool (composer)](doc/download.md#src-composer)

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

## Clear caches
me@localhost$ cv flush

## Manage extensions
me@localhost$ cv ext -Li
me@localhost$ cv dl cividiscount
me@localhost$ cv en cividiscount
me@localhost$ cv dis cividiscount
me@localhost$ cv path -x cividiscount
me@localhost$ cv url -x cividiscount

## Manage settings
me@localhost$ cv vget
me@localhost$ cv vget /mail/
me@localhost$ cv vset mailerBatchLimit=100

## Call APIs
me@localhost$ cv api3 contact.get last_name=Smith
me@localhost$ cv api4 Contact.get +w last_name=Smith

## Improvise PHP
me@localhost$ cv ev 'echo Civi::paths()->getPath("[civicrm.root]/.");'
me@localhost$ cv scr /path/to/my-script.php
me@localhost$ cv cli

## Improvise web requests
me@localhost$ cv url civicrm/dashboard --open
me@localhost$ cv url civicrm/dashboard --open -LU admin
me@localhost$ cv http civicrm/dashboard
me@localhost$ cv http civicrm/dashboard -LU admin

## Inspect events and services
me@localhost$ cv event
me@localhost$ cv event /flexmailer/
me@localhost$ cv service
me@localhost$ cv service /flexmailer/
```

If you intend to run unit-tests, and if you do *not* use `civibuild`,
then you may need to supply some additional site information (such as
the name of the test users). To do this, run:

```bash
me@localhost$ cd /var/www/my/web/site
me@localhost$ cv vars:show
me@localhost$ cv vars:fill
me@localhost$ vi ~/.cv.json
me@localhost$ cv vars:show
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

> ___NOTE___: In absence of a configuration variable, the __Automatic__ mode will behave like `CIVICRM_SETTINGS="Auto"` (in v0.3.x).
  This is tentatively planned to change in v0.4.x, where it will behave like `CIVICRM_BOOT="Auto://."`

Autocomplete
============

There is limited/experimental support for shell autocompletion based on [stecman/symfony-console-completion](https://github.com/stecman/symfony-console-completion).
To enable it:

```sh
# BASH ~4.x, ZSH
source <(cv _completion --generate-hook)

# BASH ~3.x, ZSH
cv _completion --generate-hook | source /dev/stdin

# BASH (any version)
eval $(cv _completion --generate-hook)
```

Development
===========

For more information, see [doc/develop.md](doc/develop.md).
