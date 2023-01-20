<?php declare(strict_types = 1);

return [
  'prefix' => 'Cvphar',
  'patchers' => [
      function (string $filePath, string $prefix, string $content) {
          // In some cases, `cv` references classes provided by civicrm-core or by the UF. Preserve the original names.
          $content = preg_replace(';Cvphar\(CRM_|HTML_|DB_|Drupal|JFactory|Guzzle|Civi::);', '$1', $content);
          $content = preg_replace_callback(';Cvphar\Civi\([A-Za-z0-9_\\]*);', function($m) {
            if (substr($m[1], 0, 3) === 'Cv\\') return $m[0]; // Civi\Cv is mapped.
            else return 'Civi\\' . $m[1]; // Nothing else is mapped.
          }, $content);
          return $content;
      },
  ],

  // Do not generate wrappers/aliases for `civicrm_api()` etc or various CMS-booting functions.
  'expose-global-functions' => false,
];
