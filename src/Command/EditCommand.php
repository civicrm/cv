<?php
namespace Civi\Cv\Command;

// **********************
// ** WORK IN PROGRESS **
// **********************


use Civi\Cv\Config;
use Civi\Cv\Encoder;
use Civi\Cv\Util\CliEditor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EditCommand extends CvCommand {

  /**
   * @var \Civi\Cv\Util\CliEditor
   */
  protected $editor;

  protected function configure() {
    $this
      ->setName('vars:edit')
      ->setDescription('Edit configuration values for this build');
  }

  public function __construct($name = NULL) {
    parent::__construct($name);
    $this->editor = new CliEditor();
    $this->editor->setValidator(function ($file) {
      $data = json_decode(file_get_contents($file));
      if ($data === NULL) {
        return array(
          FALSE,
          '// The JSON document was malformed. Please resolve syntax errors and then remove this message.',
        );
      }
      else {
        return array(TRUE, '');
      }
    });
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $config = Config::read();
    $oldSiteData = empty($config['sites'][CIVICRM_SETTINGS_PATH]) ? array() : $config['sites'][CIVICRM_SETTINGS_PATH];
    $oldJson = Encoder::encode($oldSiteData, 'json-pretty');
    $newJson = $this->editor->editBuffer($oldJson);
    $newSiteData = json_decode($newJson);

    print "NEW DATA\n\n====\n$newJson\n====\n";

    //    Config::update(function ($config) use ($newSiteData) {
    //      $config['sites'][CIVICRM_SETTINGS_PATH] = $newSiteData;
    //      return $config;
    //    });
  }

}
