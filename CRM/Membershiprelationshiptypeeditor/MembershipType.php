<?php

use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

class CRM_Membershiprelationshiptypeeditor_MembershipType {

  /**
   * Process the pending updated membership types.
   *
   * @return array
   * @throws CRM_Core_Exception
   */
  public function process() {
    $membershipTypesToProcess = Civi::settings()->get('membershiprelationshiptypeeditor_mtypes_process');
    if (empty($membershipTypesToProcess) || !is_array($membershipTypesToProcess)) {
      return [];
    }

    $toRemoveMembershipTypes = [];

    foreach ($membershipTypesToProcess as $membershipTypeIDToProcess => $process) {
      if (!$process) {
        continue;
      }

      $membershipType = \Civi\Api4\MembershipType::get(FALSE)
        ->addWhere('id', '=', $membershipTypeIDToProcess)
        ->execute()
        ->first();
      if (empty($membershipType)) {
        // Membership type not found, Remove it.
        \Civi::log(E::SHORT_NAME)->error("Error: Membership type with ID: {$membershipTypeIDToProcess} not found.");
        $toRemoveMembershipTypes[] = $membershipTypeIDToProcess;
        continue;
      }

      try {
        $this->deleteChildMemberships($membershipTypeIDToProcess);
        $this->updateRelatedMemberships($membershipTypeIDToProcess);
        $toRemoveMembershipTypes[] = $membershipTypeIDToProcess;
      }
      catch (Exception $e) {
        \Civi::log(E::SHORT_NAME)->error("Error processing membership type ID: {$membershipTypeIDToProcess}: " . $e->getMessage());
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
   * @param int $membershipTypeId
   * @throws CRM_Core_Exception
   * @throws CiviCRM_API3_Exception
   */
  private function updateRelatedMemberships(int $membershipTypeId) {
    // Get all the "owner" memberships for the specified membership type
    $ownerMemberships = \Civi\Api4\Membership::get(FALSE)
      ->addWhere('owner_membership_id', 'IS NULL')
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->execute();

    // Create related (inherited) memberships for each of the "owner" memberships.
    foreach ($ownerMemberships as $ownerMembership) {
      $ownerMembershipBAO = new CRM_Member_BAO_Membership();
      $ownerMembershipBAO->id = $ownerMembership['id'];
      if ($ownerMembershipBAO->find(TRUE)) {
        CRM_Member_BAO_Membership::createRelatedMemberships($ownerMembership, $ownerMembershipBAO);
      }
    }
  }

  /**
   * Delete all the child memberships for the specified membership type
   *
   * @param int $membershipTypeId
   * @throws CiviCRM_API3_Exception
   */
  private function deleteChildMemberships(int $membershipTypeId) {
    // Get all the child memberships with the specified membership type
    $ownerMemberships = \Civi\Api4\Membership::get(FALSE)
      ->addWhere('owner_membership_id', 'IS NOT NULL')
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->execute();

    // Delete all the child memberships with the specified membership type
    foreach ($ownerMemberships as $membership) {
      \Civi\Api4\Membership::delete(FALSE)
        ->addWhere('id', '=', $membership['id'])
        ->execute();
    }
  }

}
