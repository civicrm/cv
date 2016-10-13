<?php
namespace Civi\Cv\Command;

use Civi\Cv\Util\Process;
use Civi\Cv\Util\Cv;

class PopCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  /**
   * @dataProvider entityProvider
   */
  public function testPopulateSingleEntity($entity) {
    $popFile = tempnam(sys_get_temp_dir(), 'pop');
    file_put_contents($popFile, yaml_emit(array(array($entity => 1))));
    $p = Process::runOk(new \Symfony\Component\Process\Process("{$this->cv} pop $popFile"));
    $data = json_decode($p->getOutput(), 1);
    unlink($popFile);
  }

  public function entityProvider(){
    parent::setup();
    $result = $this->cvApi('entity', 'get');
    foreach($result['values'] as $entity){
      if(in_array('create', $this->cvApi($entity, 'getactions')['values'])){
        $entities[]=array($entity);
      }
    }
    return $entities;
  }
}
