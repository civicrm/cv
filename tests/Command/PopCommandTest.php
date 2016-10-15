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
    $p = Process::runOk(new \Symfony\Component\Process\Process("{$this->cv} pop $popFile -v"));
    $data = json_decode($p->getOutput(), 1);
    unlink($popFile);
  }

  public function entityProvider(){
    parent::setup();
    $supportedEntities = array(
      'Acl',
      'AclRole',
      'ActionSchedule',
      'Activity',
      'ActivityContact',
      'ActivityType',
      'Address',
      'Attachment',
      'Batch',
      'Campaign',
      'Case',
      'CaseContact',
      'CaseType',
      'Contact',
      'ContactType',
      'Contribution',
      'ContributionPage',
      'ContributionProduct',
      'ContributionRecur',
      'ContributionSoft',
      'Country',
      'CustomField',
      'CustomGroup',
      'CustomSearch',
      'CustomValue',
      'Dashboard',
      'DashboardContact',
      'DiscountCode',
      'DiscountTrack',
      'Domain',
      'Email',
      'Entity',
      'EntityBatch',
      'EntityFinancialAccount',
      'EntityFinancialTrxn',
      'EntityTag',
      'Event',
      'File',
      'FinancialAccount',
      'FinancialItem',
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
      'Job',
      'JobLog',
      'LineItem',
      'LocBlock',
      'LocationType',
      'Logging',
      'MailSettings',
      'Mailing',
      'MailingAB',
      'MailingComponent',
      'MailingContact',
      'MailingEventConfirm',
      'MailingEventQueue',
      'MailingEventResubscribe',
      'MailingEventSubscribe',
      'MailingEventUnsubscribe',
      'MailingGroup',
      'MailingJob',
      'MailingRecipients',
      'Mapping',
      'MappingField',
      'Membership',
      'MembershipBlock',
      'MembershipLog',
      'MembershipPayment',
      'MembershipStatus',
      'MembershipType',
      'MessageTemplate',
      'Note',
      'OpenID',
      'OptionGroup',
      'OptionValue',
      'Order',
      'Organization',
      'Participant',
      'ParticipantPayment',
      'ParticipantStatusType',
      'Payment',
      'PaymentProcessor',
      'PaymentProcessorType',
      'PaymentToken',
      'Pcp',
      'Phone',
      'Pledge',
      'PledgePayment',
      'Premium',
      'PriceField',
      'PriceFieldValue',
      'PriceSet',
      'PrintLabel',
      'Product',
      'Profile',
      'Relationship', // need to account for duplicate relationship error
      'RelationshipType',
      'ReportInstance', // bug in api?
      'ReportTemplate',
      'SavedSearch',
      'Setting',
      'StatusPreference',
      'Survey',
      'Tag',
      'Website',

    );
    foreach($supportedEntities as $entity){
      $entities[]=array($entity);
    }
    return $entities;
  }
}
