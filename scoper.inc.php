<?php declare(strict_types = 1);

return [
  'prefix' => 'Cvphar',
  'exclude-namespaces' => [
    // Provided by civicrm
    'Civi',
    'Guzzle',
    'Symfony\Component\DependencyInjection',

    // Drupal8+ bootstrap
    'Drupal',
    'Symfony\\Component\\HttpFoundation',
    'Symfony\\Component\\Routing',

    // Joomla bootstrap
    'TYPO3\\PharStreamWrapper',
  ],
  'exclude-classes' => [
    '/^(CRM_|HTML_|DB_|Log_)/',
    'DB',
    'Log',
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
  'exclude-files' => [
    'vendor/symfony/polyfill-php80/Resources/stubs/Stringable.php'
  ],

  // Do not generate wrappers/aliases for `civicrm_api()` etc or various CMS-booting functions.
  'expose-global-functions' => FALSE,
];
