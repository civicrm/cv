<?php

namespace Civi\Cv\Util\Pop;

class Populator {

  function __construct($entityStore, $optionStore){
    $this->entityStore = $entityStore;
    $this->optionStore = $optionStore;
  }

  function relationshipFields($entity, $fields){

    // if no relationship type has been specified, get one
    if(!isset($fields['relationship_type_id'])){
      $fields['relationship_type_id'] = $this->entityStore->getRandom('RelationshipType');
    }

    
    // get a random contact_a

    // exit;
  }
}
