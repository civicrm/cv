<?php

namespace Civi\Cv\PharOut;

/**
 * This is a work-around for a little piece of hell that is known only to Joomla-related console apps.
 *
 * The problem:
 *
 * - PHARs are archive-files. The usually include PHP code.
 * - There exists a general class of code-execution vulnerabilities involving PHARs
 *   (eg when you have an innocent-looking statement like `file_exists($userString)` and when
 *   you receive a specially-crafted upload, eg JPG with embedded PHAR - you might be
 *   tricked into executing arbitrary code).
 * - A general mitigation is to limit which PHARs you load.
 * - The library `TYPO3\PharStreamWrapper` defines a framework for limiting which PHARs you load.
 * - The library has gone through a few revisions which include BC breaks (v1, v2, v3).
 * - The library is widely used by different projects (Drupal, Joomla, etc).
 * - The library includes a basic/example policy ("only load files with .phar extension").
 * - The basic/example policy sucks for CLI tools. It's much more convenient to name CLI commands
 *   like `bin/phpunit`, `bin/composer`, `bin/cv`, rather than `bin/phpunit.phar`, `bin/composer.phar`, `bin/cv.phar`.
 * - Drupal implements a fairly sensible policy ("allow PHAR data from the main/entry-file or from *.phar files").
 *   This works well for `bin/drush`, `bin/phpunit`, `bin/cv`, etc.
 * - Joomla uses the basic/example policy.
 * - If you launch a CLI-PHAR command (eg `cv`) and then boot Joomla, it shoots your foot and breaks class-loading.
 * - When `cv` starts, we don't yet know which CMS will load -- or which (if any) version
 *   of `TYPO3\PharStreamWrapper` will be required by it. We won't know much until after they setup
 *   a policy (at which point, we may not be able to load additional resources to tune that policy).
 *
 * The "Pharout" class is work-around to fix Joomla's PHAR loading policy from outside.
 */
class PharOut {

  /**
   * Load any resources from selfsame PHAR that may be needed for PharStreamWrapper.
   * But do not actually do anything with PharStreamWrapper yet (because it's not available yet).
   */
  public static function prepare() {
    require_once __DIR__ . '/PharPolicyTrait.php';
  }

  /**
   * Remove and re-initialize the PharStreamWrapper policy.
   */
  public static function reset() {
    if (!class_exists('\TYPO3\PharStreamWrapper\Manager')) {
      // It wasn't setup, so we don't need to reset it.
      return;
    }

    \TYPO3\PharStreamWrapper\Manager::destroy();

    \TYPO3\PharStreamWrapper\Manager::initialize(
      (new \TYPO3\PharStreamWrapper\Behavior())->withAssertion(new class() implements \TYPO3\PharStreamWrapper\Assertable {
        use PharPolicyTrait;
      })
    );
    if (in_array('phar', stream_get_wrappers())) {
      stream_wrapper_unregister('phar');
      stream_wrapper_register('phar', \TYPO3\PharStreamWrapper\PharStreamWrapper::class);
    }
  }

}
