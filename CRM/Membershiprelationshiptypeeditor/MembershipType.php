<?php

use Civi\Api4\Membership;
use Civi\Api4\MembershipType;
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

class CRM_Membershiprelationshiptypeeditor_MembershipType {

  /**
   * Rebuilds the inherited memberships of the next queued membership type.
   *
   * @return int|null
   *   The membership type ID, or NULL if nothing was processed.
   */
  public function process() {
    $membershipTypeID = CRM_Membershiprelationshiptypeeditor_Queue::claimNext();
    if (!$membershipTypeID) {
      return NULL;
    }

    $membershipType = MembershipType::get(FALSE)
      ->addSelect('id', 'relationship_type_id')
      ->addWhere('id', '=', $membershipTypeID)
      ->execute()
      ->first();

    if (empty($membershipType)) {
      \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Not found. Removing from queue.");
      CRM_Membershiprelationshiptypeeditor_Queue::remove($membershipTypeID);
      return $membershipTypeID;
    }

    try {
      \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Started processing.");
      $this->deleteChildMemberships($membershipTypeID);

      if (empty($membershipType['relationship_type_id'])) {
        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. No Relationship Types set, skipping related memberships update.");
      }
      else {
        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Starting related memberships update.");
        $this->updateRelatedMemberships($membershipTypeID);
        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Completed related memberships update.");
      }

      CRM_Membershiprelationshiptypeeditor_Queue::remove($membershipTypeID);
      \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Completed processing.");
    }
    catch (\Exception $e) {
      // Left in the queue: claimNext() retries it, up to Queue::MAX_ATTEMPTS.
      \Civi::log(E::SHORT_NAME)->error("Error processing Membership Type ID: {$membershipTypeID}. " . $e->getMessage());
    }

    return $membershipTypeID;
  }

  /**
   * Update related memberships.
   *
   * @param int $membershipTypeId
   * @throws CRM_Core_Exception
   */
  private function updateRelatedMemberships(int $membershipTypeId) {
    try {
      // Get all the "owner" memberships for the specified membership type

      $ownerMemberships = Membership::get(FALSE)
        ->addWhere('owner_membership_id', 'IS NULL')
        ->addWhere('membership_type_id', '=', $membershipTypeId)
        ->execute();

      \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeId}. Retrieved all the Owner Memberships.");

      // The same relationship lookup core uses when it passes memberships on.
      $loopDetector = new CRM_Membershiprelationshiptypeeditor_InheritanceLoopDetector(
        fn(int $contactId) => array_keys(CRM_Member_BAO_Membership::checkMembershipRelationship($membershipTypeId, $contactId))
      );

      // Create related (inherited) memberships for each of the "owner" memberships.
      foreach ($ownerMemberships as $ownerMembership) {

        $ownerMembershipBAO = new CRM_Member_BAO_Membership();
        $ownerMembershipBAO->id = $ownerMembership['id'];

        $ownerMembershipId = $ownerMembership['id'];

        $loop = $loopDetector->findLoop((int) $ownerMembership['contact_id']);
        if ($loop) {
          \Civi::log(E::SHORT_NAME)->error("Membership Type ID: {$membershipTypeId}. Skipped Owner Membership ID: {$ownerMembershipId}: inheritance loops through contacts " . implode(' -> ', $loop) . '. Change the relationship types on the membership type, or the relationships between these contacts, so the membership cannot return to a contact that already has it.');
          continue;
        }

        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeId}. Search for inherited memberships for Owner Membership ID: {$ownerMembershipId}");

        try {
          if ($ownerMembershipBAO->find(TRUE)) {
            \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeId}. Create inherited memberships for Owner Membership ID: {$ownerMembershipId}");
            CRM_Member_BAO_Membership::createRelatedMemberships($ownerMembership, $ownerMembershipBAO);
          }
        }
        catch (CRM_Membershiprelationshiptypeeditor_InheritanceLoopException $e) {
          // A loop the detector missed, stopped by the pre hook guard.
          \Civi::log(E::SHORT_NAME)->error("Membership Type ID: {$membershipTypeId}. Skipped Owner Membership ID: {$ownerMembershipId}: " . $e->getMessage());
        }
      }
    } catch (Exception $e) {
      \Civi::log(E::SHORT_NAME)->error("Error processing Membership Type ID: {$membershipTypeId}: " . $e->getMessage());
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
