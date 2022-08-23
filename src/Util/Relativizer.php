<?php
namespace Civi\Cv\Util;

class Relativizer {

  /**
   * @var array
   */
  protected $prefixes;

  public function __construct() {
    $this->prefixes = [
      '[civicrm.root]/' => \Civi::paths()->getPath('[civicrm.root]/'),
      '[civicrm.packages]/' => \Civi::paths()->getPath('[civicrm.packages]/'),
      '[civicrm.files]/' => \Civi::paths()->getPath('[civicrm.files]/'),
      '[cms.root]/' => \Civi::paths()->getPath('[cms.root]/'),
    ];
  }

  /**
   * @param string $path
   *   Ex: '/var/www/sites/all/modules/civicrm/CRM/Foo.php
   * @return string
   *   Ex: '[civicrm.root]/CRM/Foo.php''
   */
  public function filter(string $path): string {
    foreach ($this->prefixes as $prefix => $prefixPath) {
      if (\CRM_Utils_File::isChildPath($prefixPath, $path)) {
        return $prefix . mb_substr($path, mb_strlen($prefixPath));
      }
    }
    return $path;
  }

}
