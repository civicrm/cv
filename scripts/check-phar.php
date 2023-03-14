#!/usr/bin/env php
<?php

// This is a sniff-test to ensure that the generated PHAR looks the way it
// should. In particular, we assert that some classes MUST have
// namespace-prefixes, and other classes MUST NOT.

global $errors, $pharFile;

if (empty($argv[1]) || !file_exists($argv[1])) {
  die("Missing argument. Ex: check-phar.php /path/to/my.phar");
}

$pharFile = $argv[1];
$errors = [];

assertMatch('src/Command/BootCommand.php', ';^namespace Civi;');
assertNotMatch('src/Command/BootCommand.php', ';^namespace Cvphar;');

assertNotMatch('vendor/symfony/console/Input/InputInterface.php', ';^namespace Symfony;');
assertMatch('vendor/symfony/console/Input/InputInterface.php', ';^namespace Cvphar.Symfony;');

assertMatch('src/Util/UrlCommandTrait.php', ';CRM_Utils_System;');
assertNotMatch('src/Util/UrlCommandTrait.php', ';Cvphar.CRM_Utils_System;');

assertMatch('src/Command/ApiCommand.php', ';civicrm_api;');
assertNotMatch('src/Command/ApiCommand.php', ';Cvphar.civicrm_api;');

assertMatch('src/CmsBootstrap.php', ';JFactory::;');
assertMatch('src/CmsBootstrap.php', ';Drupal::;');
assertNotMatch('src/CmsBootstrap.php', ';Cvphar.JFactory;');
assertNotMatch('src/CmsBootstrap.php', ';Cvphar.Drupal;');

if (empty($errors)) {
  echo "OK $pharFile\n";
}
else {
  echo "ERROR $pharFile\n";
  echo implode("", $errors);
  exit(empty($errors) ? 0 : 1);
}

########################################################################################

/**
 * Construct full name for a file (within the phar).
 */
function getFilename(?string $relpath = NULL): string {
  global $pharFile;
  $path = 'phar://' . $pharFile;
  if ($relpath != NULL) {
    $path .= '/' . $relpath;
  }
  return $path;
}

function assertMatch(string $relpath, string $regex) {
  global $errors;
  $content = explode("\n", file_get_contents(getFilename($relpath)));
  if (!preg_grep($regex, $content)) {
    $errors[] = sprintf("Failed: assertMatch(%s, %s)\n", var_export($relpath, 1), var_export($regex, 1));
  }
}

function assertNotMatch(string $relpath, string $regex) {
  global $errors;
  $content = explode("\n", file_get_contents(getFilename($relpath)));
  if (!empty($x = preg_grep($regex, $content))) {
    $errors[] = sprintf("Failed: assertNotMatch(%s, %s)\n", var_export($relpath, 1), var_export($regex, 1));
  }
}
