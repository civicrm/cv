<?php

namespace Civi\Cv\Util\Pop;

class Populator {

  function __construct($entityStore, $optionStore){
    $this->entityStore = $entityStore;
    $this->optionStore = $optionStore;
  }

  function relationshipFields($entity, &$fields){

    // if no relationship type has been specified, get one
    if(!isset($fields['relationship_type_id'])){
      $relationshipType = $this->entityStore->getRandom('RelationshipType');
    }else{
      $relationshipType = $this->entityStore->getSpecific('RelationshipType', NULL, $fields['relationship_type_id']);
    }
    $fields['relationship_type_id']=$relationshipType['id'];
    $fields['contact_id_a'] = $this->entityStore->getRandom('Contact', array('contact_type' => $relationshipType['contact_type_a']))['id'];
    $fields['contact_id_b'] = $this->entityStore->getRandom('Contact', array('contact_type' => $relationshipType['contact_type_b']))['id'];
  }
}
