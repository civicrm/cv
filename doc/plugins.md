# Plugins

Cv plugins are PHP files which register event listeners.

## Example: Add command

```php
// FILE: /etc/cv/plugin/hello-command.php
use Civi\Cv\Cv;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

if (empty($CV_PLUGIN['protocol']) || $CV_PLUGIN['protocol'] > 1) die("Expect CV_PLUGIN API v1");

Cv::dispatcher()->addListener('cv.app.commands', function($e) {
  $e['commands'][] = new class extends \Symfony\Component\Console\Command\Command {
    protected function configure() {
      $this->setName('hello')->setDescription('Say a greeting');
    }
    protected function execute(InputInterface $input, OutputInterface $output) {
      $output->writeln('Hello there!');
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
CV_PLUGIN_PATH=$HOME/.config/cv/plugin/:/etc/cv/plugin:/usr/share/cv/plugin:/usr/local/share/cv/plugin
```

<!--
Doesn't currently support project-specific plugins. This may be trickier.

After loading the global plugins, `cv` reads the the `cv.yml` and then loads any *local plugins* (i.e. *project-specific* plugins).

This sequencing meaning that some early events (e.g.  `cv.app.boot` or `cv.config.find`) are only available to *global plugins*.
-->

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