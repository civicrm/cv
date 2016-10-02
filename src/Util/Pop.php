<?php
namespace Civi\Cv\Util;

use Faker;
class Pop {

  var $added = array();
  var $randomEntityRegister = array();
  var $getFieldsRegister = array();
  var $outputHeight = 0;

  function __construct($output){
    $this->output = $output;
    $result = \civicrm_api3('entity', 'get');
    $this->faker = Faker\Factory::create();
    $this->defaultsDir = __DIR__.DIRECTORY_SEPARATOR.'Pop'.DIRECTORY_SEPARATOR;
    // add sugar for individuals, households and organisations
    $availableEntities = array_merge($result['values'], array('Individual', 'Household', 'Organization'));
    foreach($availableEntities as $entity){
      $this->availableEntities[strtolower($entity)]=$entity;
    }
  }

  function log($level, $message){
    $this->output->writeln("<$level>$message</$level>");
  }

  function load($file){
    $source = yaml_parse_file($file);
    foreach($source as $entity => $definition){
      $this->source[strtolower($entity)] = $definition;
    }
  }

  function run(){

    if($this->popFormatErrors()){
      $this->log('error', "Please correct errors in yaml.");
      return;
    }
    // go through each entity
    $this->log('info', "Adding entities...");
    foreach ($this->source as $entity => $def){
      $this->populateEntities($entity, $def);
    }
  }

  function populateEntities($entity, $definition, $parentId = null, $parentEntity = null){

    // definition may be passed as null. If so we need to convert it to an
    // empty array. Otherwise, array_replace_recursive will just return null
    if(is_null($definition)){
      $definition = array();
    }elseif(is_integer($definition)){
      $definition = array('count' => $definition);
    }

    // create a parent id field if a parentId and $parentEntity are passed
    if($parentId && $parentEntity){
      $definition["{$parentEntity}_id"]=$parentId;
    }

    // backfill defaults for this entity
    $default = yaml_parse_file("{$this->defaultsDir}default.yml");
    if($entityDefault = yaml_parse_file("{$this->defaultsDir}{$entity}.yml")){
      $default = array_replace_recursive($default, $entityDefault);
    }
    $definition = array_replace_recursive($default, $definition);


    // decide on a count, if necessary
    if(is_array($definition['count'])){
      $definition['count'] = rand(min($definition['count']) , max($definition['count']));
    }

    //convert the individual, household and organisation psuedo entities into a contact entity
    if(in_array($entity, array('individual', 'household', 'organization'))){
      $definition['contact_type'] = $entity;
      $entity = 'contact';
    }

    // remove not existent fields
    $fields = $this->getfields($entity);
    foreach($fields['values'] as $field){
      $availableFieldNames[$field['name']] = null;
    }
    $fields = array_intersect_key($definition, $availableFieldNames);

    $c = 0;
    while($c < $definition['count']){
      $id = $this->createEntity($entity, $fields);
      if(isset($definition['children'])){
        foreach($definition['children'] as $childEntity => $childDefinition){
          $this->populateEntities($childEntity, $childDefinition, $id, $entity);
        }
      }
      $c++;
    }
  }

  function getFields($entity){
    if(!isset($this->getFieldsRegister[$entity])){
      $this->getFieldsRegister[$entity] = \civicrm_api3($entity, 'getfields');
    }
    return $this->getFieldsRegister[$entity];
  }

  function createEntity($entity, $fields){

    // if required FK fields have not been set, choose one at random.
    $fieldDefs = $this->getfields($entity);
    foreach($fieldDefs['values'] as $field){
      if(!isset($fields[$field['name']]) && $field['required'] && $field['FKApiName']){
        $fields[$field['name']] = $this->getRandomEntity($field['FKApiName']);
      }
    }

    foreach($fields as $name => $field){

      if(is_array($field)){

        // if any fields are arrays select one array key at (weighted) random
        $fields[$name]=$this->selectRandom($field);

      }elseif(substr($field,0,2)=='f.'){
        $function = explode(',', substr($field,2));
        $fields[$name]=call_user_func_array(array($this->faker, array_shift($function)), $function);
      }
    }

    $result = \civicrm_api3($this->availableEntities[$entity], 'create', $fields);
    if(!$result['is_error']){
      $this->logEntity($entity, $result['id']);
      return $result['id'];
      //add to the random entity register so they can be selected in future
      $this->randomEntityRegister[$entity][]=$result['id'];
    }else{
      $this->log('error', "Could not add $entity");
      exit(1);
    }
  }

  function getRandomEntity($entity){
    if(!isset($this->randomEntityRegister[$entity])){
      // 10,000 entities is probably random enough for most people
      $result = civicrm_api3($entity, 'get', array('return' => array('id'), 'options' => array('limit' => 10000)));
      $this->randomEntityRegister[$entity]=array_keys($result['values']);
    }
    return $this->randomEntityRegister[$entity][array_rand($this->randomEntityRegister[$entity])];
  }

  function selectRandom($choices){
    $totalWeight = 0;
    foreach($choices as $choice => $weight){
      if($weight==NULL){
        $choices[$choice]=1;
      }
      $choices[$choice] = $totalWeight += $choices[$choice];

    }
    // print_r($choices);
    $selection = rand()/getrandmax()*$totalWeight;
    foreach($choices as $choice => $weight){
      if($selection < $weight){
        return $choice;
      }
    }
  }

  function logEntity($entity, $id){

    $x = 0;
    while ($x < $this->outputHeight){
      echo "\033[1A\033[K";
      $x++;
    }
    if(isset($this->added[$entity])){
      $this->added[$entity]['count']++;
      $this->added[$entity]['last_id']=$id;
    }else{
      $this->added[$entity]['count']=1;
      $this->added[$entity]['last_id']=$id;
    }
    foreach($this->added as $entity => $stats){
      // echo "{$entity}s: {$stats['count']}\n";
      echo "{$entity}s: {$stats['count']} (last id: {$stats['last_id']})\n";
    }
    $this->outputHeight=count($this->added);
  }

  function popFormatErrors(){
    $error = 0;
    foreach($this->source as $entity => $definition ){
      if(!$this->availableEntities[strtolower($entity)]){
        $this->log('error', "Unknown entity: $entity");
        $error = 1;
      }
    }
    return $error;
  }
}
