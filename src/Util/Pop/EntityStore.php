<?php

namespace Civi\Cv\Util\Pop;

/**
 * A place to cache CiviCRM entities to reduce API calls
 */
class EntityStore {

  private $store = array();

  function getRandom($entity){
    if(!isset($this->store[$entity])){
      // 10,000 entities is probably random enough for most people
      $result = civicrm_api3($entity, 'get', array('return' => array('id'), 'options' => array('limit' => 10000)));
      $this->entityStore[$entity]=array_keys($result['values']);
    }
    return $this->entityStore[$entity][array_rand($this->entityStore[$entity])];
  }

  function add($entity, $id){
    $this->store[$entity] = $id;
  }
}
