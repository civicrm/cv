<?php
namespace Civi\Cv\Command;
use Civi\Cv\Util\Process;


class PopCommandTest extends \Civi\Cv\CivilTestCase {

  public function setup() {
    parent::setup();
  }

  private function createPopFile($input) {
    $popFile = tempnam(sys_get_temp_dir(), 'pop');
    file_put_contents($popFile, yaml_emit($input));
    return $popFile;
  }

  private function runCvPop($popFile, $keep = FALSE) {
    $p = Process::runOk(new \Symfony\Component\Process\Process("{$this->cv} pop $popFile --out=json"));
    if (!$keep) {
      unlink($popFile);
    }
    return json_decode($p->getOutput(), 1);
  }

  /**
   * Attempt to create one of each supported entity
   * @dataProvider entityProvider
   */
  public function testPopulateSingleEntity($entity) {
    $popFile = tempnam(sys_get_temp_dir(), 'pop');
    file_put_contents($popFile, yaml_emit(array(array($entity => 1))));
    $p = Process::runOk(new \Symfony\Component\Process\Process("{$this->cv} pop $popFile --out=json"));
    $output = json_decode($p->getOutput(), 1);
    foreach ($output as $entityName => $entityResult) {
      $this->assertArrayNotHasKey('errors', $entityResult,
        print_r($entityResult, 1));

    }
    unlink($popFile);
  }

  /**
   * Tests the ability to define the count as an array key
   * @dataProvider countShortcutProvider
   */
  public function testCountShortcut($input) {
    $popFile = $this->createPopFile(array($input));
    $output = $this->runCvPop($popFile);
    $this->assertEquals($output[key($input)]['count'], current($input));
  }

  public function countShortcutProvider() {
    $input[][] = array('Activity' => 10);
    $input[][] = array('Phone' => 15);
    $input[][] = array('Tag' => 20);
    return $input;
  }

  public function testCountRange() {
    $x = 0;

    $data = array(
      'Phone' => '2-6',
    );

    $popFile = $this->createPopFile(array($data));
    for ($x = 0; $x <= 10; $x++) {
      $counts[] = $this->runCvPop($popFile, 1)['Phone']['count'];
    }
    $this->assertGreaterThan(1, min($counts));
    $this->assertLessThan(7, max($counts));
  }

  public function testPopulateFields() {

    $data = array(
      'Individual' => '1',
      'fields' => array(
        'first_name' => 'xxx',
        'last_name' => 'xxx',
        'middle_name' => 'xxx',
        'job_title' => 'xxx',
        'do_not_mail' => '1',
        'legal_identifier' => 'xxx',
        'birth_date' => '2001-01-01',
      ),
    );

    $popFile = $this->createPopFile(array($data));
    $contactId = $this->runCvPop($popFile)['Contact']['first_id'];

    $p = Process::runOk($this->cv("api Contact.get id=$contactId"));
    $values = json_decode($p->getOutput(), 1)['values'][$contactId];

    foreach ($data['fields'] as $key => $value) {
      $this->assertEquals($value, $values[$key]);
    }
  }

  public function testPopulateChildren() {

    $data = array(
      'Individual' => '1',
      'children' => array(
        array('ActivityContact' => '1'),
        array('Address' => '1'),
        array('Contribution' => '1'),
        array('Email' => '1'),
        // array('EntityTag' => '1'), // cannot test as create API does not return ID
        array('Grant' => '1'),
        // array('GroupContact' => '1'), // cannot test as create API does not return ID
        array('Membership' => '1'),
        array('Note' => '1'),
        array('Participant' => '1'),
        array('Phone' => '1'),
        array('Website' => '1'),
      ),
    );

    $popFile = $this->createPopFile(array($data));
    $output = $this->runCvPop($popFile);
    $contactId = $output['Contact']['first_id'];
    foreach ($data['children'] as $definition) {
      $entity = key($definition);
      $this->assertGreaterThan(0, $output[$entity]['count']);
      $p = Process::runOk($this->cv("api $entity.getsingle id={$output[$entity]['first_id']}"));
      $contactIdFromCreatedEntity = json_decode($p->getOutput(),
        1)['contact_id'];
      $this->assertEquals($contactId, $contactIdFromCreatedEntity);
    }
  }

  public function testChoose() {
    $data = array(
      'Activity' => '5',
      'fields' => array(
        'activity_type_id' => 'choose',
      ),
    );

    $popFile = $this->createPopFile(array($data));
    $output = $this->runCvPop($popFile);
    $activityId = $output['Activity']['first_id'];
    $p = Process::runOk($this->cv("api Activity.getoptions field=activity_type_id"));
    $options = array_keys(json_decode($p->getOutput(), 1)['values']);
    while ($activityId <= $output['Activity']['last_id']) {
      $p = Process::runOk($this->cv("api Activity.get id={$activityId}"));
      $values = json_decode($p->getOutput(), 1)['values'][$activityId];
      $this->assertContains($values['activity_type_id'], $options);
      $activityId++;
    }
  }

  public function testRandomEntity() {
    $data = array(
      'Participant' => '5',
      'fields' => array(
        'contact_id' => 'r.Individual',
      ),
    );

    $popFile = $this->createPopFile(array($data));
    $output = $this->runCvPop($popFile);
    $participantId = $output['Participant']['first_id'];
    while ($participantId <= $output['Participant']['last_id']) {
      $p = Process::runOk($this->cv("api Participant.get id={$participantId}"));
      $participant = json_decode($p->getOutput(), 1)['values'][$participantId];
      $p = Process::runOk($this->cv("api Contact.get id={$participant['contact_id']}"));
      $contact = json_decode($p->getOutput(), 1);
      $this->assertEquals($contact['is_error'], 0);
      $participantId++;
    }
  }

  public function entityProvider() {
    parent::setup();
    $supportedEntities = array(
      // 'Acl',
      // 'AclRole',
      // 'ActionSchedule',
      'Activity',
      'ActivityContact',
      // 'ActivityType',
      'Address',
      // 'Attachment',
      'Batch',
      'Campaign',
      // 'Case',
      // 'CaseContact',
      // 'CaseType',
      // 'Constant',
      'Contact',
      'ContactType',
      'Contribution',
      // 'ContributionPage',
      // 'ContributionProduct',
      // 'ContributionRecur',
      'ContributionSoft',
      // 'Country',
      // 'CustomField',
      'CustomGroup',
      'CustomSearch',
      // 'Cxn',
      // 'CxnApp',
      // 'CustomValue',
      // 'Dashboard',
      // 'DashboardContact',
      // 'Domain',
      'Email',
      // 'Entity',
      'EntityBatch',
      // 'EntityFinancialAccount',
      // 'EntityFinancialTrxn',
      'EntityTag',
      'Event',
      // 'Extension'
      // 'File',
      'FinancialAccount',
      // 'FinancialItem',
      'FinancialTrxn',
      'FinancialType',
      'Grant',
      'Group',
      'GroupContact',
      'GroupNesting',
      'GroupOrganization',
      'Household',
      'Im',
      'Individual',
      // 'Job',
      // 'JobLog',
      // 'LineItem',
      // 'LocBlock',
      'LocationType',
      // 'Logging',
      // 'MailSettings',
      // 'Mailing',
      // 'MailingAB',
      // 'MailingComponent',
      // 'MailingContact',
      // 'MailingEventConfirm',
      // 'MailingEventQueue',
      // 'MailingEventResubscribe',
      // 'MailingEventSubscribe',
      // 'MailingEventUnsubscribe',
      // 'MailingGroup',
      // 'MailingJob',
      // 'MailingRecipients',
      'Mapping',
      // 'MappingField',
      'Membership',
      'MembershipBlock',
      // 'MembershipLog',
      'MembershipPayment',
      'MembershipStatus',
      'MembershipType',
      'MessageTemplate',
      // 'Navigation',
      'Note',
      'OpenID',
      'OptionGroup',
      'OptionValue',
      // 'Order',
      'Organization',
      'Participant',
      'ParticipantPayment',
      // 'ParticipantStatusType',
      // 'Payment',
      // 'PaymentProcessor',
      // 'PaymentProcessorType',
      // 'PaymentToken',
      // 'Pcp',
      'Phone',
      // 'Pledge',
      // 'PledgePayment',
      // 'Premium', // It looks like there are some manatory fields that are not marked as such
      // 'PriceField',
      // 'PriceFieldValue',
      // 'PriceSet',
      'PrintLabel',
      'Product',
      // 'Profile',
      // 'RecurringEntity',
      'Relationship', // We need to account for Duplicate Relationship Exception
      'RelationshipType',
      // 'ReportInstance', // Didn't appear to work. Might be that we need to specify more fields but the API didn't pick that up.
      'ReportTemplate',
      // 'RuleGroup',
      'SavedSearch',
      'Setting',
      // 'SmsProvider',
      'StatusPreference',
      'Survey',
      // 'SurveyRespondant',
      // 'System',
      // 'SystemLog',
      'Tag',
      // 'UFField',
      // 'UFGroup',
      // 'UFJoin',
      // 'UFMatch',
      // 'User',
      'Website',
      // WordReplacement
    );
    foreach ($supportedEntities as $entity) {
      $entities[] = array($entity);
    }
    return $entities;
  }

  public function testFaker() {
    $data = array(
      'Contact' => '1',
      'fields' => array(
        'middle_name' => 'f.words,5,1',
      ),
    );

    $popFile = $this->createPopFile(array($data));
    $output = $this->runCvPop($popFile);
    $p = Process::runOk($this->cv("api Contact.get id={$output['Contact']['first_id']}"));
    $contact = json_decode($p->getOutput(), 1);
    $middleNameArray = explode(' ',
      $contact['values'][$contact['id']]['middle_name']);
    $this->assertEquals(count($middleNameArray), 5);
  }

}
