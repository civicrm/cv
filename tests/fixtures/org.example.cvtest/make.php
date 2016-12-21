#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') {
  die("This tool can only be run from command line.");
}

if (empty($argv[1])) {
  die(sprintf("usage: %s <zipfile>\n", $argv[0]));
}


if (file_exists($argv[1])) {
  unlink($argv[1]);
}

$zip = new ZipArchive();
if (TRUE !== $zip->open($argv[1], ZipArchive::CREATE)) {
  printf("Failed to open %s\n", $argv[1]);
  exit(1);
}

$zip->addFile('LICENSE.txt', 'org.example.cvtest/LICENSE.txt');
$zip->addFile('cvtest.php', 'org.example.cvtest/cvtest.php');
$zip->addFile('info.xml', 'org.example.cvtest/info.xml');

$ok = $zip->close();

if ($ok) {
  printf("Created %s\n", $argv[1]);
  exit(0);
}
else {
  printf("Failed to create %s\n", $argv[1]);
  exit(1);
}
