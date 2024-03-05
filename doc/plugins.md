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

* `cv.app.boot` (*global-only*): Fires immediately when the application starts
* `cv.app.run` (*global-only*): Fires when the application begins executing a command
* `cv.app.commands` (*global-only*): Fires when the application builds a list of available commands
   * __Argument__: `$e['commands`]`: alterable list of commands
