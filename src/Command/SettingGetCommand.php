<?php
namespace Civi\Cv\Command;

use Civi\Core\SettingsBag;
use Civi\Cv\Util\SettingTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SettingGetCommand extends CvCommand {

  use StructuredOutputTrait;
  use SettingTrait;

  protected function configure() {
    $C = '<comment>';
    $_C = '</comment>';
    $I = '<info>';
    $_I = '</info>';

    $this
      ->setName('setting:get')
      ->setAliases(['vget'])
      ->setDescription('Read CiviCRM settings')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (args,json)', 'args')
      ->configureOutputOptions([
        'tabular' => TRUE,
        'shortcuts' => TRUE,
        'fallback' => 'table',
        'availColumns' => 'scope,key,value,default,explicit,mandatory,layer',
        'defaultColumns' => 'scope,key,value,layer',
        'specialFormats' => ['civicrm.settings.php'],
      ])
      ->addOption('scope', NULL, InputOption::VALUE_REQUIRED, 'Domain to configure', 'domain')
      ->addArgument('name', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'An setting name or regex')
      ->setHelp("Read CiviCRM settings

{$C}Name Filter{$_C}

    By default, display all settings. You may optionally lookup specific settings by name
    or by regular expression.

    {$C}cv setting:get {$_C}{$I}mailerJobSize{$_I}                    (specific settings)
    {$C}cv setting:get {$_C}{$I}mailerJobSize mailerJobsMax{$_I}      (specific settings)
    {$C}cv setting:get /{$_C}{$I}mail{$_I}{$C}/{$_C}                           (any settings that involve \"mail\")

{$C}Output Modes{$_C}

    By default, display settings and their current values in a summary table.
    For more detailed information, you may mix {$C}-v{$_C}, {$C}--columns{$_C}, and/or {$C}-out{$_C}.

    {$C}cv setting:get -v{$_C}                               (detailed report, console)
    {$C}cv setting:get --out={$_C}{$I}json-pretty{$_I}{$C} --columns={$_C}{$I}*{$_I}    (detailed report, json)
    {$C}cv setting:get --out={$_C}{$I}civicrm.settings.php{$_I}       (export for use in civicrm.settings.php)

{$C}Setting Scope{$_C}

    All CiviCRM settings are formally attached to a scope, such as a {$I}Contact{$_I} or {$I}Domain{$_I}.
    For most tasks in most deployments, there is only one important scope ({$I}domain #1{$_I}).
    In some edge-cases, you may need to target a specific scope Here are some example scopes:

    {$C}cv setting:get --scope={$_C}{$I}domain{$_I}                   (default domain)
    {$C}cv setting:get --scope={$_C}{$I}domain:*{$_I}                 (all domains)
    {$C}cv setting:get --scope={$_C}{$I}domain:12{$_I}                (domain #12)
    {$C}cv setting:get --scope={$_C}{$I}contact{$_I}{$C} --user={$_C}{$I}admin{$_I}     (admin's contact)
    {$C}cv setting:get --scope={$_C}{$I}contact:201{$_I}              (contact #201)
");
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $hasExplicit = method_exists(SettingsBag::class, 'hasExplicit') /* v6.4 */ ? 'hasExplicit' : 'hasExplict';
    $filter = $this->createSettingFilter($input->getArgument('name'));

    $result = [];
    foreach ($this->findSettings($input->getOption('scope')) as $settingScope => $settingBag) {
      /** @var \Civi\Core\SettingsBag $settingBag */
      $meta = $this->getMetadata($settingScope);

      foreach ($settingBag->all() as $settingKey => $settingValue) {
        if (!$filter($settingKey)) {
          continue;
        }
        [$encode, $decode] = $this->codec($meta, $settingKey);
        $row = [
          'scope' => $settingScope,
          'key' => $settingKey,
          'value' => $decode($settingBag->get($settingKey)),
          'default' => $decode($settingBag->getDefault($settingKey)),
          'explicit' => $decode($settingBag->getExplicit($settingKey)),
          'mandatory' => $decode($settingBag->getMandatory($settingKey)),
          'layer' => $settingBag->getMandatory($settingKey) !== NULL ? 'mandatory' : ($settingBag->$hasExplicit($settingKey) ? 'explicit' : 'default'),
        ];
        $result[] = $row;
      }
    }

    $this->sendSettings($input, $output, $result);
    return 0;
  }

}
