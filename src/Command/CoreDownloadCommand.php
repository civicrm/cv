<?php
namespace Civi\Cv\Command;

// use Civi\Cv\Encoder;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
// use Symfony\Component\Console\Question\ChoiceQuestion;

class CoreDownloadCommand extends BaseCommand {

  use \Civi\Cv\Util\SetupCommandTrait;
  use \Civi\Cv\Util\DebugDispatcherTrait;

  protected function configure() {
    $this
      ->setName('core:download')
      ->setDescription('Download a CiviCRM release')
      ->addOption('cms', '', InputOption::VALUE_REQUIRED, 'Specify the CMS')
      ->addOption('release', '', InputOption::VALUE_REQUIRED, 'Specify the release')
      ->addOption('l10n', '', InputOption::VALUE_NONE, 'Download localization files')
      ->setHelp('
Dowload the Drupal release of CiviCRM

$ cv core:download drupal
');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {

    if(is_dir('civicrm')){
      $output->writeln('<error>Refusing to download CiviCRM. civicrm directory already exists here</error>');
      exit;
    }


    $this->cacheDir = "{$_SERVER['HOME']}/.cache/cv";

    if(!($release = $input->getOption('release'))){
      $release = $this->getLatestRelease();
    }
    $cms = $input->getOption('cms');

    $ext = in_array($cms, ['wordpress', 'joomla']) ? 'zip' : 'tar.gz';

    $tarName = "civicrm-{$release}-{$cms}.{$ext}";
    
    if(!file_exists("{$this->cacheDir}/{$tarName}")){
      $this->cacheTar($tarName, $output);
    }
    $output->writeln("<info>Extracting {$tarName}...</info>");
    if($ext == 'zip'){
      exec("unzip {$this->cacheDir}/{$tarName}");
    }else{
      exec("tar -xf {$this->cacheDir}/{$tarName}");
    }
    
    if($input->getOption('l10n')){
      $l10nTarName = "civicrm-{$release}-l10n.tar.gz";
      if(!file_exists("{$this->cacheDir}/{$l10nTarName}")){
        $this->cacheTar($l10nTarName, $output);
      }
      $output->writeln("<info>Extracting {$l10nTarName}...</info>");
      exec("tar -xf {$this->cacheDir}/{$l10nTarName}");
    }
  }

  protected function cacheTar($tarName, OutputInterface $output){
    
    if(!is_dir($this->cacheDir)){
      mkdir($this->cacheDir);
    }
    $output->writeln("<info>Dowloading {$tarName}...</info>");
    file_put_contents("{$this->cacheDir}/{$tarName}", file_get_contents("https://download.civicrm.org/{$tarName}"));
  }

  protected function getLatestRelease(){
    $latest = file_get_contents('https://latest.civicrm.org/stable.php');
    if(!$latest){
      Throw new \Exception('Error looking up latest release');
    }
    return $latest;
  }

}
