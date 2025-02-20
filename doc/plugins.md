# Plugins

Cv plugins are PHP files which register event listeners.

## Quick Start

Let's create a global plugin. Add the file `/etc/cv/plugin/hello-command.php` with this content:

```php
use Civi\Cv\Cv;
use Civi\Cv\Command\CvCommand;
use CvDeps\Symfony\Component\Console\Input\InputInterface;
use CvDeps\Symfony\Component\Console\Input\InputArgument;
use CvDeps\Symfony\Component\Console\Output\OutputInterface;

if (empty($CV_PLUGIN['protocol']) || $CV_PLUGIN['protocol'] > 1) {
  die(__FILE__ . " has only been tested with CV_PLUGIN API v1");
}

Cv::dispatcher()->addListener('cv.app.commands', function($e) {

  $e['commands'][] = (new CvCommand('hello'))
    ->setDescription('Say a greeting')
    ->addArgument('name', InputArgument::REQUIRED, 'Name of the person to greet')
    ->setCode(function(InputInterface $input, OutputInterface $output) {
      $output->writeln('Hello, ' . $input->getArgument('name'));
      return 0;
    });

});
```

Key points:

* You can create a global plugin by putting a PHP file in a suitable folder.
* The important namespaces are `\Civi\Cv` (*classes and helpers from `cv`*) and `\CvDeps` (*third-party dependencies bundled with `cv`*).
* `cv` is built with the [Symfony Console (5.x) library](https://symfony.com/doc/5.x/components/console.html).
* If there is a major change in how plugins are loaded, you will get an error notice.
* To develop functionality, we use helpers like `Cv::io()` and events like `cv.app.*`.
* Specifically, to register a command, one uses `cv.app.command` and makes an instance of `CvCommand`.

Each of these topics is explored more in the subsequent sections.

## Plugin loading

*Global plugins* are loaded from the `CV_PLUGIN_PATH`.  All `*.php` files in
`CV_PLUGIN_PATH` will be loaded automatically during startup.  Plugins are
deduped by name (with earlier folders having higher precedence).

If otherwise unspecified, the default value of `CV_PLUGIN_PATH` is:

```bash
CV_PLUGIN_PATH=$HOME/.cv/plugin/:/etc/cv/plugin:/usr/local/share/cv/plugin:/usr/share/cv/plugin
```

<!--
Doesn't currently support project-specific plugins. This may be trickier.

After loading the global plugins, `cv` reads the the `cv.yml` and then loads any *local plugins* (i.e. *project-specific* plugins).

This sequencing meaning that some early events (e.g.  `cv.app.boot` or `cv.config.find`) are only available to *global plugins*.
-->

## Namespacing

The plugin itself may live in a global namespace or its own namespace.

Plugins execute within `cv`'s process, so they are affected by `cv`'s namespace-prefixing rules:

* External dependencies (eg CiviCRM, Drupal, WordPress) are not provided by `cv`. They do not have prefixing.
  Access these with their canonical names (eg `Civi\*`, `CRM_*`, `Drupal\*`).
* Internal dependencies (eg Symfony Console) are bundled with `cv`. They are generally prefixed, though the
  concrete names vary. To maximize portability, access these classes with the logical alias `CvDeps\*` (eg `CvDeps\Symfony\Component\Console\*`).

## `Cv` helpers

The `\Civi\Cv\Cv` facade provides some helpers for implementing functionality:

* Input/Output
    * __`Cv::io()`__: Get the Symfony "Style" interface for current subcommand. (*This provides high-level functions for interaction with the console user.*)
    * __`Cv::input()`__: Get the Symfony "Input" interface for current subcommand. (*This is a mid-level helper for examining CLI parameters/arguments.*)
    * __`Cv::output()`__: Get the Symfony "Output" interface for current subcommand. (*This is a mid-level helper for basic formatting of the output.*)
    * (*During cv's initial bootstrap, there is no active subcommand. These may return stubs.*)
* Event
    * __`Cv::dispatcher()`__: Get a reference to the dispatcher service. Add listeners and/or fire events.
    * __`Cv::filter(string $eventName, array $eventData)`__: Fire a basic event to modify `$eventData`.
* Reflection
    * __`Cv::app()`__: Get a reference to the active/top-level `cv` command.
    * __`Cv::plugins()`__: Get a reference to the plugin subsystem.
    * __`Cv::ioStack()`__: Manage active instances of the input/output services.

## Events

* `cv.app.boot`: Fires immediately when the application starts
   * __Argument__: `$e['app']`: Reference to the `Application` object
* `cv.app.commands`: Fires when the application builds a list of available commands
   * __Argument__: `$e['commands`]`: alterable list of commands
* `cv.app.run`: Fires when the application begins executing a command
   * __Argument__: `$e['app']`: Reference to the `Application` object
* `cv.app.site-alias`: Fires if the command is called with an alias (eg `cv @mysite ext:list`)
   * __Argument__: `$e['alias']`: The name of the alias
   * __Argument__: `$e['app']`: Reference to the `Application` object
   * __Argument__: `$e['input']`: Reference to the `InputInterface`
   * __Argument__: `$e['output']`: Reference to the `OutputInterface`
   * __Argument__: `$e['argv']`: Raw/original arguments passed to the current command
   * __Argument__: `$e['transport']`: Alterable callback (output). Fill in a value to specify how to forward the command to the referenced site.
   * __Argument__: `$e['exec']`: Non-alterable callback (input). Use this if you need to immediately call the action within the current process.

(Note: When subscribing to an event like `cv.app.site-alias`, you may alternatively subscribe to the wildcard `*.app.site-alias`. In the future, this should allow you hook into adjacent commands like civix and coworker.)

## Commands

You can register new subcommands within `cv`. `cv` includes the base-class from Symfony Console, and its adds another base-class. Compare:

* `CvDeps\Symfony\Component\Console\Command\Command` is the original building-block from Symfony Console. It can define and parse CLI arguments, but it does *not* bootstrap CiviCRM or CMS. It may be suitable for some basic commands. Documentation is provided by upstream.
* `Civi\Cv\Command\CvCommand` (v0.3.56+) is an extended version. It automatically boots CiviCRM and CMS. It handles common options like `--user`, `--url`, and `--level`, and it respect environment-variables like `CIVICRM_BOOT`.

For this document, we focus on `CvCommand`.

Subcommands can be written in a few coding-styles, such as the *fluent-object* style or a *structured-class* style. Compare:

```php
## Fluent-object style of command
$command = (new CvCommand('my-command'))
  ->setDescription('Say a greeting')
  ->addArgument('name', InputArgument::REQUIRED, 'Name of the person to greet')
  ->setCode(function($input, $output) {
    $output->writeln('Hello, ' . $input->getArgument('name'));
    return 0;
  });
```

```php
## Structured-class style
class MyCommand extends CvCommand {

  public function configure() {
    $this->setName('my-command');
    $this->setDescription('Say a greeting');
    $this->addArgument('name', InputArgument::REQUIRED, 'Name of the person to greet');
  }

  public functione execute(InputInterface $input, OutputInterface $output): int {
    $output->writeln('Hello, ' . $input->getArgument('name'));
    return 0;
  }
}

$command = new MyCommand();
```

Both styles can be used in any kind of `cv` plugin, but you may find some affinities:

* The fluent-object style is shorter, and it doesn't require class-loading or subfolders. If your aim is to deliver the plugin as one `*.php` file, then this style may fit better.
* The structured-class style is more verbose and more organized (classes, namespaces, subfolders). If you implement several commands, this can keep things tidy. But it requires some glue (such as class-loading). If your aim is to bundle commands into CiviCRM extension, then this style may fit better.

## `$CV_PLUGIN` data

When loading a plugin, the variable `$CV_PLUGIN` is prepopulated with information about the plugin and its environment.

* __Property__: `$CV_PLUGIN['appName']`: Logical name of the CLI application
* __Property__: `$CV_PLUGIN['appVersion']`: Version of the main application
* __Property__: `$CV_PLUGIN['name']`: Logical name of the plugin
* __Property__: `$CV_PLUGIN['file']`: Full path to the plugin-file
