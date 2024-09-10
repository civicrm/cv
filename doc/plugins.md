# Plugins

Cv plugins are PHP files which register event listeners.

## Example: Add command

```php
// FILE: /etc/cv/plugin/hello-command.php
use Civi\Cv\Cv;
use CvDeps\Symfony\Component\Console\Input\InputInterface;
use CvDeps\Symfony\Component\Console\Output\OutputInterface;
use CvDeps\Symfony\Component\Console\Command\Command;

if (empty($CV_PLUGIN['protocol']) || $CV_PLUGIN['protocol'] > 1) die("Expect CV_PLUGIN API v1");

Cv::dispatcher()->addListener('cv.app.commands', function($e) {
  $e['commands'][] = new class extends Command {
    protected function configure() {
      $this->setName('hello')->setDescription('Say a greeting');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int {
      $output->writeln('Hello there!');
      return 0;
    }
  };
});
```

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
   * __Argument__: `$e['transport']`: Alternable callback (output). Fill in a value to specify how to forward the command to the referenced site.
   * __Argument__: `$e['exec']`: Non-alterable callback (input). Use this if you need to immediately call the action within the current process. 

(Note: When subscribing to an event like `cv.app.site-alias`, you may alternatively subscribe to the wildcard `*.app.site-alias`. In the future, this should allow you hook into adjacent commands like civix and coworker.)

## `Cv` helpers

The `\Civi\Cv\Cv` facade provides some helpers for implementing functionality:

* Event helpers
    * __`Cv::dispatcher()`__: Get a reference to the dispatcher service. Add listeners and/or fire events.
    * __`Cv::filter(string $eventName, array $eventData)`__: Fire a basic event to modify `$eventData`.
* I/O helpers
    * __`Cv::io()`__: Get the Symfony "Style" interface for current subcommand
    * __`Cv::input()`__: Get the Symfony "Input" interface for current subcommand
    * __`Cv::output()`__: Get the Symfony "Output" interface for current subcommand
    * (*During cv's initial bootstrap, there is no active subcommand. These return stubs.*)

## `$CV_PLUGIN` data

When loading a plugin, the variable `$CV_PLUGIN` is prepopulated with information about the plugin and its environment.

* __Property__: `$CV_PLUGIN['appName']`: Logical name of the CLI application
* __Property__: `$CV_PLUGIN['appVersion']`: Version of the main application
* __Property__: `$CV_PLUGIN['name']`: Logical name of the plugin
* __Property__: `$CV_PLUGIN['file']`: Full path to the plugin-file
