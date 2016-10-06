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
    if(!$source){
      echo "Error: could not parse yaml file: ($file)\n";
      exit(1);
    }
    foreach($source as $x => $instruction){
      foreach($instruction as $key => $element){
        $this->source[$x][strtolower($key)]=$element;
      }
    }
  }

  function processInstruction($instruction){
    $orig = $instruction;
    // if this instruction is of the format entity: [integer]
    if(isset($instruction['fields'])){
      $def['fields'] = $instruction['fields'];
      unset($instruction['fields']);
    }
    if(isset($instruction['children'])){
      $def['children'] = $instruction['children'];
      unset($instruction['children']);
    }
    if(count($instruction) != 1){
      echo "Error: badly formatted instruction:\n";
      echo yaml_emit($orig);
      exit;
    }elseif(!$this->availableEntities[key($instruction)]){
      echo "Error: unknown entity: ".key($instruction)."\n";
    }
    $entity = key($instruction);
    $def['count']=current($instruction);
    if(!isset($def['count'])){
      $def['count']=1;
    }
    // print_r($def);exit;
    return array($entity, $def);
  }

  function run(){

    // go through each entity
    $this->log('comment', "Adding entities...");
    foreach ($this->source as $instruction){
      list($entity, $def) = $this->processInstruction($instruction);
      $this->populateEntities($entity, $def);
    }
    $this->summarizeEntities();
  }

  function populateEntities($entity, $definition, $parentId = null, $parentEntity = null){

    if(!isset($definition['fields'])){
      $definition['fields']=array();
    }
    if(!isset($definition['children'])){
      $definition['children']=array();
    }
    // create a parent id field if a parentId and $parentEntity are passed
    if($parentId && $parentEntity){
      $definition['fields']["{$parentEntity}_id"]=$parentId;
    }
    // backfill defaults for this entity
    $default = yaml_parse_file("{$this->defaultsDir}default.yml");
    if($entityDefault = yaml_parse_file("{$this->defaultsDir}{$entity}.yml")){
      $default['fields'] = array_replace_recursive($default['fields'], $entityDefault['fields']);
      if(isset($entityDefault['children']) && isset($definition['children'])){
        $definition['children'] = array_merge($definition['children'], $entityDefault['children']);
      }
    }
    // print_r($definition['children']);
    // print_r($definition['fields']);
    $definition['fields'] = array_replace_recursive($default['fields'], $definition['fields']);
    // print_r($definition);
    // print_r('s');exit;
    // echo "---\nadding $entity";
    // print_r($definition);



    // decide on a count, if necessary
    if(strpos($definition['count'], '-')){
      $definition['count'] = explode('-', $definition['count']);
      $definition['count'] = rand(min($definition['count']) , max($definition['count']));
    }

    //convert the individual, household and organisation psuedo entities into a contact entity
    if(in_array($entity, array('individual', 'household', 'organization'))){
      $definition['fields']['contact_type'] = $entity;
      $entity = 'contact';
    }

    // remove fields that were defined in yaml but do not exist in api definition
    $definedFields = $this->getFields($entity);
    foreach($definedFields as $definedField){
      $definedFieldNames[$definedField['name']] = null;
    }
    $fields = array_intersect_key($definition['fields'], $definedFieldNames);

    $c = 0;
    while($c < $definition['count']){

      $id = $this->createEntity($entity, $fields);
      if(isset($definition['children'])){;
        foreach($definition['children'] as $childInstruction){
          list($childEntity, $childDef) = $this->processInstruction($childInstruction);
          $this->populateEntities($childEntity, $childDef, $id, $entity);
        }
      }
      $c++;
    }
  }

  function createEntity($entity, $fields){
    $this->availableEntities[$entity];
    // if required FK fields have not been set, choose one at random.
    $fieldDefs = $this->getFields($entity);
    foreach($fieldDefs as $field){
      if(!isset($fields[$field['name']]) && $field['required'] & $field['name']!='id'){
        if($field['FKApiName']){
          $fields[$field['name']] = $this->getRandomEntity($field['FKApiName']);
        }
        elseif($field['pseudoconstant']){
          $this->getRandomPseudoConstant($field['pseudoconstant']['optionGroupName']);
        }
      }
    }
    foreach($fields as $name => &$field){
      // if this field is an array, make a 'weighted random' selection of a key
      if(is_array($field)){
        $field = $this->selectRandom($field);
      }
      if(strpos($field,'r.')===0 || strpos($field,'rov.')===0){
        $field = $this->random($field);
      }elseif(strpos($field,'a.')===0){
        $field=$this->arrayify($field);
      //if it begins with 'f.' pass it to faker
    }elseif(stripos($field,'f.')===0){
        $field=$this->fake($field);
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

  function getFields($entity){
    $entity = $this->availableEntities[$entity];
    if(!isset($this->getFieldsRegister[$entity])){
      $this->getFieldsRegister[$entity] = \civicrm_api3($entity, 'getfields')['values'];
    }
    return $this->getFieldsRegister[$entity];
  }

  // expects (but doesn't check) that field starts 'f.'
  function fake($field){
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

  // expects (but doesn't check) that field starts 'r.'
  function random($field){
    list($object, $name) = explode('.',$field);
    switch ($object){
      case 'r':
        return $this->getRandomEntity($name);
      case 'rov':
        return $this->getRandomOptionValue($name);
    }
  }

  function arrayify($choice){
    $fields = explode(',', substr($choice,2));
    foreach($fields as &$field){
      if(strpos($field,'f.')){
        $field = $this->fake($field);
      }
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
  function getRandomOptionValue($optionGroup){
    if(!isset($this->randomPseudoConstantRegister[$optionGroup])){
      $ogr = civicrm_api3('OptionGroup', 'get', array('return' => array('id'), 'name' => $optionGroup));
      $ovr = civicrm_api3('OptionValue', 'get', array('option_group_id' => $ogr['id']));
      $this->randomPseudoConstantRegister[$optionGroup]=$ovr['values'];
    }
    return $this->randomPseudoConstantRegister[$optionGroup][array_rand($this->randomPseudoConstantRegister[$optionGroup])]['label'];
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
    // return;
    $entity = $this->availableEntities[$entity];
    $x = 0;
    while ($x < $this->outputHeight){
      echo "\033[1A";
      $x++;
    }
    if(isset($this->added[$entity])){
      $this->added[$entity]['count']++;
      $this->added[$entity]['last_id']=$id;
    }else{
      $this->added[$entity]['count']=1;
      $this->added[$entity]['first_id']=$id;
      $this->added[$entity]['last_id']=$id;
    }
    ksort($this->added);
    foreach($this->added as $entity => $stats){
      $this->output->writeln("\033[K<fg=green>{$entity}s: </><fg=green>{$stats['count']}</>");
    }
    $this->outputHeight=count($this->added);
  }

  function summarizeEntities(){
    $x = 0;
    while ($x < $this->outputHeight){
      echo "\033[1A";
      $x++;
    }
    foreach($this->added as $entity => $stats){
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
