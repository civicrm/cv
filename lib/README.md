# cv-lib

`cv-lib` is a subpackage provided by `cv`. It defines the essential core of `cv` -- locating and booting CiviCRM.

The canonical home for developing this code is in [civicrm/cv](https://github.com/civicrm/cv). It will be periodically published to the read-only
mirror [civicrm/cv-lib](https://github.com/civicrm/cv-lib) to facilitate usage by other projects.

## Installation

```bash
composer require civicrm/cv-lib
```

## Primary API

The library provides a handful of supported classes:

* `Civi\Cv\CmsBootstrap` supports the standard boot protocol. In this protocol, we search for a recognized UF/CMS, start
  that, and then start CiviCRM. The advantage of this protocol is that it is more representative of a typical
  HTTP-request. (Events and add-ons supported by UF/CMS and CRM will tend to work more normally.)

    Basic usage:

    ```php
    Civi\Cv\CmsBootstrap::singleton()->bootCms()->bootCivi();
    ```

    Or you can pass in options:

    ```php
    $options = [...];
    Civi\Cv\CmsBootstrap::singleton()
      ->addOptions($options)
      ->bootCms()
      ->bootCivi();
    ```

    End-users may fine-tune the behavior by setting `CIVICRM_BOOT` (as documented in `cv`).

* `Civi\Cv\Bootstrap` supports the legacy boot protocol. In this protocol, we search for `civicrm.settings.php` and
  start CiviCRM. Finally, we use `civicrm-core` API's to start the associated UF/CMS.

    Basic usage:

    ```php
    $options = [...];
    \Civi\Cv\Bootstrap::singleton()->boot($options);
    \CRM_Core_Config::singleton();
    \CRM_Utils_System::loadBootStrap([], FALSE);
    ```

    End-users may fine-tune the behavior by setting `CIVICRM_SETTING` (as documented in `cv`).

Both bootstrap mechanisms accept an optional set of hints and overrides.

For example, by default, `cv-lib` will print errors to STDERR, but you can override the
handling of messages:

```php
// Disable all output
$options['log'] = new \Psr\Log\NullLogger();

// Enable verbose logging to STDOUT/STDERR
$options['log'] = new \Civi\Cv\Log\StderrLogger('Bootstrap', TRUE);

// Use bridge between psr/log and symfony/console
$options['log'] = new \Symfony\Component\Console\Logger\ConsoleLogger($output);

// Use the console logger from cv cli. (Requires symfony/console. Looks a bit prettier.)
public function execute(InputInterface $input, OutputInterface $output) {
  ...
  $options['output'] = $output;
  ...
}
```

For more info about `$options`, see the docblocks.

## Experimental API

Other classes are included, but their contracts are subject to change. These
include higher-level helpers for building Symfony Console apps that incorporate
Civi bootstrap behaviors.

* `BootTrait` has previously suggested as an experimentally available API
  (circa v0.3.44).  It changed significantly (circa v0.3.56), where
  `configureBootOptions()` was replaced by  `$bootOptions`, `mergeDefaultBootDefinition()`,
  and `mergeBootDefinition()`.
* As an alternative, consider the classes `BaseApplication` and `CvCommand` if you aim
  to build a tool using Symfony Console and Cv Lib.
