<?php
namespace Civi\Cv\Command;

use Civi\Cv\Encoder;
use Civi\Cv\Util\ExtensionTrait;
use Civi\Cv\Util\Process;
use Civi\Cv\Util\StructuredOutputTrait;
use Civi\Cv\Util\UrlCommandTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PasswordResetCommand extends CvCommand {

  use ExtensionTrait;
  use StructuredOutputTrait;
  use UrlCommandTrait;

  protected function configure() {
    $this
      ->setName('password-reset')
      ->setAliases(['pw'])
      ->setDescription('Generate a password reset link for a user (for CiviCRM Standalone only).')
      ->configureOutputOptions()
      ->configureUrlOptions()
      ->addOption('uid', NULL, InputOption::VALUE_OPTIONAL, 'User ID')
      ->addOption('expires', NULL, InputOption::VALUE_OPTIONAL, 'Expiry time in minutes (default: 60 minutes)')
      // The original contract only displayed one URL. We subsequently added support for list/csv/table output which require multi-record orientation.
      // It's ambiguous whether JSON/serialize formats should stick to the old output or multi-record output.
      ->addOption('tabular', NULL, InputOption::VALUE_NONE, 'Force display in multi-record mode. (Enabled by default for list,csv,table formats.)')
      ->setHelp('
Generate a password reset link for a contact.

Examples: Generate a password reset link for the first available user
  cv password-reset
  cv pw

Examples: Generate a password reset link for a specific user
  cv password-reset --uid=2

Examples: Generate a password reset link for a specific user that expires in 24h
  cv password-reset --uid=2 --expires=1440
');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $expires = $input->getOption('expires', 60) * 60 + time();
    $uid = $input->getOption('uid');

    if (CIVICRM_UF != 'Standalone') {
      throw new \Exception('This command only works with CiviCRM Standalone');
    }

    if ($uid) {
      $valid = \CRM_Core_DAO::singleValueQuery('SELECT uf_id FROM civicrm_uf_match WHERE is_active = 1 AND uf_id = %1', [
        1 => [$uid, 'Positive'],
      ]);
      if (!$valid) {
        throw new \Exception('The specified uid is not valid or not active.');
      }
    }
    else {
      $uid = \CRM_Core_DAO::singleValueQuery('SELECT uf_id FROM civicrm_uf_match WHERE is_active = 1 ORDER BY uf_id ASC LIMIT 1');
    }

    $token = \Civi::service('crypto.jwt')->encode([
      'exp' => $expires,
      'sub' => "uid:$uid",
      'scope' => \Civi\Api4\Action\User\PasswordReset::PASSWORD_RESET_SCOPE,
    ]);
    \Civi\Api4\User::update(FALSE)
      ->addValue('password_reset_token', $token)
      ->addWhere('id', '=', $uid)
      ->execute();

    $url = \CRM_Utils_System::url('civicrm/login/password', 'token=' . $token, TRUE, NULL, NULL, TRUE);
    $this->sendResult($input, $output, $url);

    return 0;
  }

}
