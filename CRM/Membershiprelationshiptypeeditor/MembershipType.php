<?php

class CRM_Membershiprelationshiptypeeditor_MembershipType {

  /**
   * Process the pending updated membership types.
   *
   * @return array
   * @throws CRM_Core_Exception
   */
  public function process() {
    $membershipTypesToProcess = Civi::settings()->get('membershiprelationshiptypeeditor_mtypes_process');

    $toRemoveMembershipTypes = [];

    if (is_array($membershipTypesToProcess)) {
      foreach ($membershipTypesToProcess as $membershipTypeToProcess => $value) {
        if ($value) {
          try {

            try {
              civicrm_api3('MembershipType', 'getsingle', array(
                'id' => $membershipTypeToProcess,
              ));
            }
            catch (CiviCRM_API3_Exception $e) {
              // Membership type not found, Remove it.
              continue;
              $toRemoveMembershipTypes[] = $membershipTypeToProcess;
            }

            $this->deleteChildMemberships($membershipTypeToProcess);
            $this->updateRelatedMemberships($membershipTypeToProcess);

            $toRemoveMembershipTypes[] = $membershipTypeToProcess;

          }
          catch (CiviCRM_API3_Exception $e) {

          }
        }
      }

    }

    foreach ($toRemoveMembershipTypes as $toRemoveMembershipType) {
      if (isset($membershipTypesToProcess[$toRemoveMembershipType])) {
        unset($membershipTypesToProcess[$toRemoveMembershipType]);
      }
    }

    Civi::settings()->set('membershiprelationshiptypeeditor_mtypes_process', $membershipTypesToProcess);

    return $toRemoveMembershipTypes;
  }

  /**
   * Update related memberships.
   *
   * @param $membershipTypeId
   * @throws CRM_Core_Exception
   * @throws CiviCRM_API3_Exception
   */
  private function updateRelatedMemberships($membershipTypeId) {
    $ownerMemberships = civicrm_api3('Membership', 'get', [
      'sequential'          => 1,
      'owner_membership_id' => ['IS NULL' => 1],
      'membership_type_id'  => $membershipTypeId,
      'options' => ['limit' => 0],
    ]);

    $ownerMemberships = $ownerMemberships['values'];

    foreach ($ownerMemberships as $ownerMembership) {
      $ownerMembershipBAO = new CRM_Member_BAO_Membership();
      $ownerMembershipBAO->id = $ownerMembership['id'];
      if ($ownerMembershipBAO->find(TRUE)) {
        CRM_Member_BAO_Membership::createRelatedMemberships($ownerMembership, $ownerMembershipBAO);
      }
    }
  }

  /**
   * Delete child memberships
   *
   * @param $membershipTypeId
   * @throws CiviCRM_API3_Exception
   */
  private function deleteChildMemberships($membershipTypeId) {
    $memberships = civicrm_api3('Membership', 'get', [
      'sequential'          => 1,
      'owner_membership_id' => ['IS NOT NULL' => 1],
      'membership_type_id'  => $membershipTypeId,
    ]);

    $memberships = $memberships['values'];
    foreach ($memberships as $membership) {
      civicrm_api3('Membership', 'delete', array(
        'id' => $membership['id'],
      ));
    }
  }

}
