<?php declare(strict_types = 1);

return [
  'prefix' => 'Cvphar',
  'exclude-namespaces' => [
    'Civi',
    'Guzzle',
    'Drupal',

    // we don't really use these, but CmsBootstrap needs to apply D8+ bootstrap protocol
    'Symfony\\Component\\HttpFoundation',
    'Symfony\\Component\\Routing',
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
