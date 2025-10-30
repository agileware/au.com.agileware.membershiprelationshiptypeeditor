<?php

use Civi\Api4\Membership;
use Civi\Api4\MembershipType;
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

class CRM_Membershiprelationshiptypeeditor_MembershipType {

  /**
   * Process the queued updated membership types.
   * Note that this function will remove all membership types from the queue that are processed successfully
   *
   * @return array
   * @throws CRM_Core_Exception
   */
  public function process() {
    // Retrieve the Membership types in the queue "setting"
    $membershipTypesToProcess = Civi::settings()->get('membershiprelationshiptypeeditor_mtypes_process');
    if (empty($membershipTypesToProcess) || !is_array($membershipTypesToProcess)) {
      return [];
    }

    $toRemoveMembershipTypes = [];

    $loadedMembershipTypes = MembershipType::get(FALSE)
                                           ->addWhere('id', 'IN', array_filter($membershipTypesToProcess))
                                           ->execute()
                                           ->indexBy('id');

    foreach (array_keys($membershipTypesToProcess) as $membershipTypeIDToProcess) {
      $membershipType = $loadedMembershipTypes[$membershipTypeIDToProcess] ?? NULL;

      if (empty($membershipType)) {
        // Membership type not found, Remove it.
        \Civi::log(E::SHORT_NAME)->error("Error: Membership type with ID: {$membershipTypeIDToProcess} not found.");
        $toRemoveMembershipTypes[] = $membershipTypeIDToProcess;
        continue;
      }

      // Remove and then re-add all inherit memberships for the given relationship type, then remove from the queue.
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

    // Reset the queue to include only membership types that failed to process this time.
    Civi::settings()->set('membershiprelationshiptypeeditor_mtypes_process', $membershipTypesToProcess);

    return $toRemoveMembershipTypes;
  }

  /**
   * Update related memberships.
   *
   * @param int $membershipTypeId
   * @throws CRM_Core_Exception
   */
  private function updateRelatedMemberships(int $membershipTypeId) {
    // Get all the "owner" memberships for the specified membership type
    $ownerMemberships = Membership::get(FALSE)
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
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function deleteChildMemberships(int $membershipTypeId) {
    // Get all the parent memberships with the specified membership type
    $ownerMembershipIds = Membership::get(FALSE)
      ->addSelect('owner_membership_id', 'COUNT(id) as inherited')
      ->addWhere('owner_membership_id', 'IS NOT NULL')
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->setGroupBy(['owner_membership_id', 'membership_type_id'])
      ->execute()
      ->column('owner_membership_id');

    foreach ($ownerMembershipIds as $membership_id) {
      // Get the Contact ID for the owner membership
      $membership = Membership::get(FALSE)
        ->addSelect('membership_type_id', 'contact_id')
        ->addWhere('id', '=', $membership_id)
        ->execute()
        ->first();

      // Find all the expected inherited membership contacts
      $related = CRM_Member_BAO_Membership::checkMembershipRelationship($membership['membership_type_id'], $membership['contact_id'], CRM_Core_Action::ADD & CRM_Core_Action::UPDATE);
      $related = array_filter($related, fn($status) => $status == CRM_Contact_BAO_Relationship::CURRENT);

      // Create the delete action
      $deleteAction = Membership::delete(FALSE)
        ->addWhere('owner_membership_id', '=', $membership_id);

      // Exclude inherited memberships for the expected contacts from deletion if any found.
      if(!empty($related)) {
        $deleteAction->addWhere('contact_id', 'NOT IN', array_keys($related));
      }

      // Execute removal of inherited memberships that no longer meet the conditions.
      $deleteAction->execute();
    }
  }

}
