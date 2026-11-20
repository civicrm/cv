<?php

use Civi\Cv\Cv;

if (defined('CIVICRM_DSN')) {
  fprintf(STDERR, "WARNING: Cv plugins initialized after CiviCRM booted. Second stage loading may not work as expected.\n");
}

// Define the hook listener before Civi boots.
$GLOBALS['CIVICRM_FORCE_MODULES'][] = 'cvplugin_loader';

/**
 * @param $config
 * @param array|NULL $flags
 *   Only defined on 5.65+.
 * @return void
 * @see \CRM_Utils_Hook::config()
 */
function cvplugin_loader_civicrm_config($config, $flags = NULL): void {
  static $loaded = FALSE;
  if (!$loaded) {
    if ($flags === NULL || !empty($flags['civicrm'])) {
      $loaded = TRUE;
      Cv::plugins()->initExtensions();
    }
  }
}
