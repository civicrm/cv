<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\BootTrait;
use Civi\Cv\Util\SettingTrait;
use Civi\Cv\Util\StructuredOutputTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SettingSetCommand extends BaseCommand {

  use BootTrait;
  use StructuredOutputTrait;
  use SettingTrait;

  protected function configure() {
    $C = '<comment>';
    $_C = '</comment>';
    $I = '<info>';
    $_I = '</info>';

    $this
      ->setName('setting:set')
      ->setAliases(['vset'])
      ->setDescription('Update CiviCRM settings')
      ->addOption('in', NULL, InputOption::VALUE_REQUIRED, 'Input format (args,json)', 'args')
      ->configureOutputOptions([
        'tabular' => TRUE,
        'shortcuts' => ['table', 'list'],
        'fallback' => 'table',
        'availColumns' => 'scope,key,value,default,explicit,mandatory,layer',
        'defaultColumns' => 'scope,key,value,layer',
      ])
      ->addOption('dry-run', 'N', InputOption::VALUE_NONE, 'Preview the API call. Do not execute.')
      ->addOption('scope', NULL, InputOption::VALUE_REQUIRED, 'Domain to configure', 'domain')
      ->addArgument('key=value', InputArgument::IS_ARRAY)
      ->setHelp("Update CiviCRM settings

If you'd like to inspect the behavior more carefully, try using {$I}--dry-run{$_I} ({$I}-N{$_I}).

{$C}Specification: Piped JSON${_C}

    {$C}echo{$_C} {$I}JSON{$_I} | {$C}cv setting:set${_C} {$C}--in=json${_C}

{$C}Specification: JSON-ish Key-Value${_C}

    {$C}cv setting:set${_C} [{$I}KEY{$_I}={$I}VALUE{$_I}]... [{$I}JSON-OBJECT{$_I}]...

    Use ${I}KEY{$_I}={$I}VALUE{$_I} to set an input to a specific value. The value may be a bare string
    or it may be JSON (beginning with '[' or '{' or '\"').

    Use {$I}JSON-OBJECT{$_I} if you want to pass several fields as one pure JSON string.
    A parameter which begins with '{' will be interpreted as a JSON expression.

{$C}Specification: +Options${_C}

    {$C}cv setting:set {$_C}[{$C}+{$_C}{$I}OP{$_I}{$C} {$_C}{$I}EXPR{$_I}]...

    Each \"+Option\" is an instruction for how to perform an update.
    These are useful for manipulating arrays, as in:

    Option         Examples
    {$C}+d{$_C}|{$C}+delete{$_C}     +d contact_reference_options.0
    {$C}+m{$_C}|{$C}+merge{$_C}      +m contact_reference_options.0=3
    {$C}+m{$_C}|{$C}+merge{$_C}      +m contact_reference_options[]=3

    They are also useful for manipulating objects, as in:

    {$C}+d{$_C}|{$C}+delete{$_C}     +d mailing_backend.outBound_option
    {$C}+m{$_C}|{$C}+merge{$_C}      +m mailing_backend.outBound_option=3

{$C}Setting Scope{$_C}

    All CiviCRM settings are formally attached to a scope, such as a {$I}Contact{$_I} or {$I}Domain{$_I}.
    For most tasks in most deployments, there is only one important scope ({$I}domain #1{$_I}).
    In some edge-cases, you may need to target a specific scope Here are some example scopes:

    {$C}cv setting --scope={$_C}{$I}domain{$_I}                (default domain)
    {$C}cv setting --scope={$_C}{$I}domain:*{$_I}              (all domains)
    {$C}cv setting --scope={$_C}{$I}domain:12{$_I}             (domain #12)
    {$C}cv setting --scope={$_C}{$I}contact{$_I}{$C} --user={$_C}{$I}admin{$_I}  (admin's contact)
    {$C}cv setting --scope={$_C}{$I}contact:201{$_I}           (contact #201)
");
    $this->configureBootOptions();
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $C = '<comment>';
    $_C = '</comment>';
    $I = '<info>';
    $_I = '</info>';

    $this->boot($input, $output);
    $errorOutput = is_callable([$output, 'getErrorOutput']) ? $output->getErrorOutput() : $output;

    $result = [];
    foreach ($this->findSettings($input->getOption('scope')) as $settingScope => $settingBag) {
      /** @var \Civi\Core\SettingsBag $settingBag */
      $meta = $this->getMetadata($settingScope);

      $params = $this->parseSettingParams($input, $settingBag, $meta);
      if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
        $output->writeln("{$I}Params{$_I}: " . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      }

      foreach ($params as $settingKey => $settingValue) {
        [$encode, $decode] = $this->codec($meta, $settingKey);
        if ($settingBag->getMandatory($settingKey) !== NULL) {
          $errorOutput->writeln("<comment>WARNING: \"$settingKey\" has a mandatory override. Stored settings may be inoperative.</comment>");
        }
        if (!$input->getOption('dry-run')) {
          $settingBag->set($settingKey, $encode($settingValue));
        }

        $row = [
          'scope' => $settingScope,
          'key' => $settingKey,
          'value' => $input->getOption('dry-run') ? ($decode($settingBag->getMandatory($settingKey)) ?: $settingValue) : $decode($settingBag->get($settingKey)),
          'default' => $decode($settingBag->getDefault($settingKey)),
          'explicit' => $input->getOption('dry-run') ? $settingValue : $decode($settingBag->getExplicit($settingKey)),
          'mandatory' => $decode($settingBag->getMandatory($settingKey)),
          'layer' => $settingBag->getMandatory($settingKey) !== NULL ? 'mandatory' : ($settingBag->hasExplict($settingKey) ? 'explicit' : 'default'),
        ];
        $result[] = $row;
      }
    }

    $this->sendSettings($input, $output, $result);

    return 0;
  }

}
