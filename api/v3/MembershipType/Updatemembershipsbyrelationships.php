<?php
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

/**
 * MembershipType.Updatemembershipsbyrelationships API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 */
function _civicrm_api3_membership_type_Updatemembershipsbyrelationships_spec(&$spec) {

}

/**
 * MembershipType.Updatemembershipsbyrelationships API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_membership_type_Updatemembershipsbyrelationships($params) {
  $membershipTypes = new CRM_Membershiprelationshiptypeeditor_MembershipType();
  $membershipTypesProcessed = $membershipTypes->process();

  $response = [
    'processed' => $membershipTypesProcessed,
    'count'     => count($membershipTypesProcessed),
  ];

  return civicrm_api3_create_success($response, $params, 'MembershipType', 'Updatemembershipsbyrelationships');
}
