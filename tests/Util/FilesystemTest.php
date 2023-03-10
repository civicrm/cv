<?php
namespace Civi\Cv\Util;

/**
 * @group std
 * @group util
 */
class FilesystemTest extends \PHPUnit\Framework\TestCase {

  public function dataIsDescendent() {
    return array(
      array('/ex', '/ex', FALSE),
      array('/ex', '/ex/', FALSE),
      array('/ex/', '/ex', FALSE),
      array('/ex/', '/ex/', FALSE),
      array('/ex', '/ex/one', FALSE),
      array('/ex', '/ex/one/', FALSE),
      array('/ex/', '/ex/one', FALSE),
      array('/ex/', '/ex/one/', FALSE),
      array('/ex', '/ex1', FALSE),
      array('/ex', '/ex1/', FALSE),
      array('/ex/', '/ex1', FALSE),
      array('/ex/', '/ex1/', FALSE),
      array('/ex/one', '/ex', TRUE),
      array('/ex/one', '/ex/', TRUE),
      array('/ex/one/', '/ex', TRUE),
      array('/ex/one/', '/ex/', TRUE),
      array('/ex1', '/ex', FALSE),
      array('/ex1', '/ex/', FALSE),
      array('/ex1/', '/ex', FALSE),
      array('/ex1/', '/ex/', FALSE),

    );
  }

  /**
   * @param string $child
   * @param string $parent
   * @param bool $expected
   * @dataProvider dataIsDescendent
   */
  public function testIsDescendent($child, $parent, $expected) {
    $fs = new Filesystem();
    $this->assertEquals($expected, $fs->isDescendent($child, $parent));
  }

}
