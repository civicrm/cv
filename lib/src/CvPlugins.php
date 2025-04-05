<?php
namespace Civi\Cv;

class CvPlugins {

  /**
   * @var string[]
   */
  private $paths;

  private $plugins;

  /**
   * Load any plugins.
   *
   * This will scan any folders listed in CV_PLUGIN_PATH. If CV_PLUGIN_PATH
   * is undefined, then the default will include paths under /home, /etc,
   * and /usr/../share
   *
   * @param array $pluginEnv
   *   Description the current application environment.
   *   Ex: ['appName' => 'cv', 'appVersion' => '0.3.50']
   */
  public function init(array $pluginEnv) {
    if (getenv('CV_PLUGIN_PATH')) {
      $this->paths = explode(PATH_SEPARATOR, getenv('CV_PLUGIN_PATH'));
    }
    else {
      $this->paths = ['/etc/cv/plugin', '/usr/local/share/cv/plugin', '/usr/share/cv/plugin'];
      if (getenv('HOME')) {
        array_unshift($this->paths, getenv('HOME') . '/.cv/plugin');
      }
      elseif (getenv('USERPROFILE')) {
        array_unshift($this->paths, getenv('USERPROFILE') . '/.cv/plugin');
      }
      if (getenv('XDG_CONFIG_HOME')) {
        array_unshift($this->paths, getenv('XDG_CONFIG_HOME') . '/cv/plugin');
      }
    }

    // Always load internal plugins
    $this->paths['builtin'] = dirname(__DIR__) . '/plugin';

    $this->plugins = [];
    foreach ($this->paths as $path) {
      if (file_exists($path) && is_dir($path)) {
        foreach ($this->findFiles($path, '/\.php$/') as $file) {
          $pluginName = preg_replace(';(\d+-)?(.*)(@\w+)?\.php;', '\\2', basename($file));
          if ($pluginName === basename($file)) {
            throw new \RuntimeException("Malformed plugin name: $file");
          }
          if (!isset($this->plugins[$pluginName])) {
            $this->plugins[$pluginName] = $file;
          }
          else {
            fprintf(STDERR, "WARNING: Plugin %s has multiple definitions (%s, %s)\n", $pluginName, $file, $this->plugins[$pluginName]);
          }
        }
      }
    }

    ksort($this->plugins);
    foreach ($this->plugins as $pluginName => $pluginFile) {
      // FIXME: Refactor so that you can add more plugins post-boot `load("/some/glob*.php")`
      $this->load($pluginEnv + [
        'protocol' => 1,
        'name' => $pluginName,
        'file' => $pluginFile,
      ]);
    }
  }

  /**
   * @param array $CV_PLUGIN
   *   Description of the plugin being loaded.
   *   Keys:
   *   - version: Protocol version (ex: "1")
   *   - name: Basenemae of the plugin (eg `hello.php`)
   *   - file: Logic filename (eg `/etc/cv/plugin/hello.php`)
   * @return void
   */
  protected function load(array $CV_PLUGIN) {
    include $CV_PLUGIN['file'];
  }

  /**
   * @return string[]
   */
  public function getPaths(): array {
    return $this->paths;
  }

  /**
   * @return array
   */
  public function getPlugins(): array {
    return $this->plugins;
  }

  private function findFiles(string $path, string $regex): array {
    // NOTE: scandir() works better than glob() in PHAR context.
    $files = preg_grep($regex, scandir($path));
    return array_map(function ($f) use ($path) {
      return "$path/$f";
    }, $files);
  }

}
