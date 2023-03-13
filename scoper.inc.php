<?php declare(strict_types = 1);

return [
  'prefix' => 'Cvphar',
  'exclude-namespaces' => ['Civi', 'Guzzle', 'Drupal'],
  'exclude-classes' => ['/^(CRM_|HTML_|DB_)/', 'JFactory', 'Civi', 'Drupal'],
  'exclude-functions' => ['/^civicrm_api/'],

  // Do not generate wrappers/aliases for `civicrm_api()` etc or various CMS-booting functions.
  'expose-global-functions' => false,
];
