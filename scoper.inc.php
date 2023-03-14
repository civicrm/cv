<?php declare(strict_types = 1);

return [
  'prefix' => 'Cvphar',
  'exclude-namespaces' => [
    // Provided by civicrm
    'Civi',
    'Guzzle',

    // Drupal8+ bootstrap
    'Drupal',
    'Symfony\\Component\\HttpFoundation',
    'Symfony\\Component\\Routing',

    // Joomla bootstrap
    'TYPO3\\PharStreamWrapper',
  ],
  'exclude-classes' => [
    '/^(CRM_|HTML_|DB_)/',
    'JFactory',
    'Civi',
    'Drupal',
  ],
  'exclude-functions' => [
    '/^civicrm_/',
    '/^wp_.*/',
    '/^(drupal|backdrop|user|module)_/',
    't',
  ],

  // Do not generate wrappers/aliases for `civicrm_api()` etc or various CMS-booting functions.
  'expose-global-functions' => FALSE,
];
