<?php

use Civi\Api4\MembershipType;
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

/**
 * MembershipType.Queueallmembershiptypesforupdate API
 *
 * Loads all active membership types and queues them to update inherited relationships.
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_membership_type_Queueallmembershiptypesforupdate($params) {
  // Load every active membership type id
  $membershipTypes = MembershipType::get(FALSE)
    ->addSelect('id')
    ->addWhere('is_active', '=', TRUE)
    ->execute();

  // Queue setting is [ membership_type_id => TRUE, ... ]
  $membershipTypes = array_fill_keys($membershipTypes->column('id'), TRUE);

  // Override for the next update run
  Civi::settings()->set('membershiprelationshiptypeeditor_mtypes_process', $membershipTypes);

  // Always success.
  return civicrm_api3_create_success([
    'success' => count($membershipTypes),
  ], $params, 'MembershipType', 'Queueallmembershipshiptypeforupdate');
}
