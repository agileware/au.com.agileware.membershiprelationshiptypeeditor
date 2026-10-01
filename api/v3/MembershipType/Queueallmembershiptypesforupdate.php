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

  // Add to, rather than overwrite, the queue: types already waiting keep their
  // place and attempt count, and inactive types queued by hand stay queued.
  $membershipTypeIds = $membershipTypes->column('id');
  CRM_Membershiprelationshiptypeeditor_Queue::add($membershipTypeIds);

  // Always success.
  return civicrm_api3_create_success([
    'success' => count($membershipTypeIds),
  ], $params, 'MembershipType', 'Queueallmembershiptypesforupdate');
}
