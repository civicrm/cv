<?php
namespace Civi\Cv\Util;

use Faker;
class Pop {

  // Summarise the process (number of entities imported, etc.)
  var $summary = array();

  // Place to store and retreive entities
  var $entityStore = array();

  // Place to store and retreive option Groups
  var $optionGroupStore = array();

  // The types of entity that we can populate
  var $availableEntities  = array();

  // The fields we can populate for each type of entity
  var $availableFields  = array();

  function __construct($output){

    // Initialise output interface
    $this->output = $output;

    // Initialise faker
    $this->faker = Faker\Factory::create();

    // Get available entities
    $result = \civicrm_api3('entity', 'get');

    // Pretend that Individuals, Organisations and Households are also entities
    $availableEntities = array_merge($result['values'], array('Individual', 'Household', 'Organization'));
    foreach($availableEntities as $entity){
      $this->availableEntities[strtolower($entity)]=$entity;
    }

    // Define where to find Pop yml files
    $this->defaultEntityDir = __DIR__.DIRECTORY_SEPARATOR.'Pop'.DIRECTORY_SEPARATOR;

    $this->defaultDefinition = yaml_parse_file("{$this->defaultEntityDir}default.yml");
  }

  function process($file){

    $this->log('comment', "Adding entities...");

    // load instructions from files
    $this->load($file);

    // process each instruction
    foreach ($this->instructions as $instruction){

      // create a definition from each instruction
      $definition = $this->translate($instruction);

      // add values from default
      $definition = $this->backfill($definition);

      // populate entities based on the definition
      $this->populate($definition);
    }

    // summarise the process
    $this->summarize();
  }

  function log($level, $message){
    $this->output->writeln("<$level>$message</$level>");
  }

  function load($file){

    // load the yaml file
    $yaml = yaml_parse_file($file);
    if(!$yaml){
      echo "Error: could not open yaml file: ($file)\n";
      exit(1);
    }

    // for each instruction, lowercase the argument names
    foreach($yaml as $n => $instruction){
      foreach($instruction as $key => $argument){
        $this->instructions[$n][strtolower($key)]=$argument;
      }
    }
  }

  function translate($instruction){
    // clone the $instruction for debugging purposes
    $original = $instruction;

    // empty definition to be populated
    $definition = array(
      'fields' => array(),
      'children' => array(),
    );

    // if the instruction includes fields, add them
    if(isset($instruction['fields'])){
      $definition['fields']=$instruction['fields'];
      unset($instruction['fields']);
    }

    // if the instruction includes children, add them
    if(isset($instruction['children'])){
      $definition['children']=$instruction['children'];
      unset($instruction['children']);
    }

    // at this point, valid instructions should be a one
    // element array of form array(Entity => count). Exit if
    // this is not the case.
    if(count($instruction) != 1){
      echo "Error: badly formatted instruction:\n";
      echo yaml_emit($original);
      exit(1);
    }

    // define the entity and check that it is available
    $definition['entity'] = key($instruction);
    if(!isset($this->availableEntities[$definition['entity']])){
      echo "Error: could not find entity: {$definition['entity']}\n";
      echo yaml_emit($original);
      exit(1);
    }

    // check that the count is valid (i.e. an integer or a range specified by
    // two integers seperated by a dash
    $definition['count'] = current($instruction);
    if(!preg_match('/^\d+(\-\d+)?$/', $definition['count'])){
      echo "Error: invalid value for count: {$definition['count']}\n";
      echo yaml_emit($original);
      exit(1);
    }
    return $definition;
  }

  function backfill($definition) {

    // get defaults for this entity, if they exist
    $entityDefault = yaml_parse_file("{$this->defaultEntityDir}{$definition['entity']}.yml");

    // backfill with default fields for this entity
    if(isset($entityDefault['fields'])){
      $definition['fields'] = array_replace_recursive($entityDefault['fields'], $definition['fields']);
    }

    // backfill with global default fields
    if(isset($this->defaultDefinition['fields'])){
      $definition['fields'] = array_replace_recursive($this->defaultDefinition['fields'], $definition['fields']);
    }

    // only allow fields available for this api
    foreach($this->getAvailableFields($definition['entity']) as $field){
      $availableFields[$field['name']] = null;
    }
    $definition['fields'] = array_intersect_key($definition['fields'], $availableFields);

    // add any childrend defined in the default entity
    if(isset($entityDefault['children'])){
      $definition['children'] = array_merge($definition['children'], $entityDefault['children']);
    }

    return $definition;
  }

  function getAvailableFields($entity){
    if(!isset($this->availableFields[$entity])){
      $this->availableFields[$entity] = \civicrm_api3($entity, 'getfields', array('api_action'=> 'create'))['values'];
    }
    return $this->availableFields[$entity];
  }

  function getRequiredFields($entity){
    if(!isset($this->requiredFields[$entity])){
      foreach($this->getAvailableFields($entity) as $availableField){
        if($availableField['api.required']){
          if(isset($availableField['FKApiName'])){
            $this->requiredFields[$entity][$availableField['name']] = 'Entity';
          }elseif(isset($availableField['pseudoconstant'])){
            $this->requiredFields[$entity][$availableField['name']] = 'Option';
          }
        }
      }
    }
    return $this->requiredFields[$entity];
  }



  // create a set of entities from a definition
  function populate($definition, $parent = null){

    // if count is a range, decide how many entities to create
    if(strpos($definition['count'], '-')){
      $definition['count'] = explode('-', $definition['count']);
      $definition['count'] = rand(min($definition['count']) , max($definition['count']));
    }

    // if this is an individual, household or organisation, convert it to a
    // contact with an appropriatly defined contact_type
    if(in_array($definition['entity'], array('individual', 'household', 'organization'))){
      $definition['fields']['contact_type'] = $definition['entity'];
      $definition['entity'] = 'contact';
    }

    // if this is a child of another entity, populate the parent id
    if($parent){
      $definition['fields']["{$parent['entity']}_id"]=$parent['id'];
    }

    // create each entity
    $count = 0;
    while($count < $definition['count']){
      $createdEntity = $this->createEntity($definition['entity'], $definition['fields']);

      // create children if necessary
      if(isset($definition['children'])){;
        foreach($definition['children'] as $childInstruction){
          $childDefinition = $this->translate($childInstruction);
          $childDefinition = $this->backfill($childDefinition);
          $this->populate($childDefinition, $createdEntity);
        }
      }
      $count++;
    }
  }

  function createEntity($entity, $fields){
    // go through fields, making substitutions where necessary
    foreach($fields as $name => &$value){

      // if value is an array, select one at (weighted) random
      if(is_array($value)){
        $value = $this->weightedRandomSelect($value);
      }

      // if we are using a modifier, run the appropriate function

      if(strpos($value,"r.")===0){
        $value = $this->getRandomEntity(substr($value,2));
      }elseif(stripos($value,"f.")===0){
        $value = $this->getFake($value);
      }
    }

    // add any required fields using sensible defaults
    foreach($this->getRequiredFields($entity) as $requiredFieldName => $requiredFieldType){
      if(!isset($fields[$requiredFieldName])){
        if($requiredFieldType == 'Entity'){
          $fields[$requiredFieldName] = $this->getRandomEntity($entity);
        }
        if($requiredFieldType == 'Option'){
          $fields[$requiredFieldName] = $this->getRandomOption($entity, $fields[$requiredFieldName]);
        }
      }
    }


    $result = \civicrm_api3($this->availableEntities[$entity], 'create', $fields);
    if(!$result['is_error']){
      $this->logEntity($entity, $result['id']);
      return array('entity' => $entity, 'id' => $result['id']);
      //add to the random entity register so they can be selected in future
      $this->entityStore[$entity][]=$result['id'];
    }else{
      $this->log('error', "Could not add $entity");
      exit(1);
    }
  }

  function weightedRandomSelect($array){

    $total = 0;
    foreach($array as $choice => $value){
      if($value==NULL){
        $value=1;
      }
      $choices[$choice] = $total += $value;
    }
    $selection = rand()/getrandmax()*$total;
    foreach($choices as $choice => $value){
      if($selection < $value){
        return $choice;
      }
    }
  }


  function getFake($field){
    $function = explode(',', substr($field,2));
    $output = call_user_func_array(array($this->faker, array_shift($function)), $function);
    if ($output instanceof \DateTime) {
      $output = $output->format('Y-m-d H:i:s');
    }

    if($field[0]=='F'){
      $output=ucfirst($output);
    }
    return $output;
  }

  function getRandomEntity($entity){
    if(!isset($this->entityStore[$entity])){
      // 10,000 entities is probably random enough for most people
      $result = civicrm_api3($this->availableEntities[$entity], 'get', array('return' => array('id'), 'options' => array('limit' => 10000)));
      $this->entityStore[$entity]=array_keys($result['values']);
    }
    return $this->entityStore[$entity][array_rand($this->entityStore[$entity])];
  }
  function getRandomOption($entity, $field){
    if(!isset($this->$optionStore[$entity][$field])){
      $this->optionStore[$entity][$field] = civicrm_api3('Address', 'getoptions', array(
        'sequential' => 1,
        'field' => "location_type_id",
      ))['values'];
    }
    return $this->optionStore[$entity][$field][array_rand($this->optionStore[$entity][$field])]['value'];
  }

  function logEntity($entity, $id){
    $entity = $this->availableEntities[$entity];
    $x = 0;
    while ($x < count($this->summary)){
      echo "\033[1A";
      $x++;
    }
    if(isset($this->summary[$entity])){
      $this->summary[$entity]['count']++;
      $this->summary[$entity]['last_id']=$id;
    }else{
      $this->summary[$entity]['count']=1;
      $this->summary[$entity]['first_id']=$id;
      $this->summary[$entity]['last_id']=$id;
    }
    ksort($this->summary);
    foreach($this->summary as $entity => $stats){
      $this->output->writeln("\033[K<fg=green>{$entity}s: </><fg=green>{$stats['count']}</>");
    }
  }

  function summarize(){
    $x = 0;
    while ($x < count($this->summary)){
      echo "\033[1A";
      $x++;
    }
    foreach($this->summary as $entity => $stats){
      $this->output->write("\033[K<fg=green>{$entity}s: {$stats['count']} ");
      if($stats['first_id'] && $stats['first_id']==$stats['last_id']){
        $this->output->writeln("({$stats['first_id']})</>");
      }elseif($stats['first_id'] < $stats['last_id']){
        $this->output->writeln("({$stats['first_id']} to {$stats['last_id']})</>");
      }else{
        $this->output->writeln("(unknown ids)</>");
      }
    }
  }
}
