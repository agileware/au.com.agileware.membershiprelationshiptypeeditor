<?php
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

/**
 * MembershipType.Addmembershiptypesinqueue API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_membership_type_Addmembershiptypesinqueue_spec(&$spec) {
  $spec['membershiptypes'] = [
    'title'        => E::ts('Membership Types'),
    'name'         => 'membershiptypes',
    'api.required' => TRUE,
    'type'         => CRM_Utils_Type::T_INT,
    'api.multiple' => 1,
    'options' => CRM_Member_PseudoConstant::membershipType(),
  ];
}

/**
 * MembershipType.Addmembershiptypesinqueue API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_membership_type_Addmembershiptypesinqueue($params) {
  $membershipTypes = $params['membershiptypes'];
  if ($membershipTypes != '' && !is_array($membershipTypes)) {
    $membershipTypes = [$membershipTypes];
  }

  $typesToProcess = Civi::settings()->get('membershiprelationshiptypeeditor_mtypes_process');
  if ($typesToProcess == '' || $typesToProcess == NULL) {
    $typesToProcess = [];
  }

  foreach ($membershipTypes as $membershipType) {
    $typesToProcess[$membershipType] = TRUE;
  }
  Civi::settings()->set('membershiprelationshiptypeeditor_mtypes_process', $typesToProcess);
  return civicrm_api3_create_success([
    'success' => count($membershipTypes),
  ], $params, 'MembershipType', 'Updatemembershipsbyrelationships');
}
