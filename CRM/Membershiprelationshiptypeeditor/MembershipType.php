<?php

use Civi\Api4\Membership;
use Civi\Api4\MembershipType;
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

class CRM_Membershiprelationshiptypeeditor_MembershipType {

  /**
   * Processes a single membership type from the queue and removes it.
   *
   * @return int|null The ID of the processed membership type, or null if queue was empty.
   * @throws CRM_Core_Exception
   */
  public function process() {
    // Retrieve the Membership types queue from CiviCRM settings.
    $membershipTypesToProcess = \Civi::settings()->get('membershiprelationshiptypeeditor_mtypes_process');

    if (empty($membershipTypesToProcess) || !is_array($membershipTypesToProcess)) {
      return NULL;
    }

    // Identify the first ID in the associative array.
    reset($membershipTypesToProcess);
    $membershipTypeID = (int) key($membershipTypesToProcess);

    if (!$membershipTypeID) {
      return NULL;
    }

    // Fetch the specific membership type
    $membershipType = MembershipType::get(FALSE)
      ->addSelect('id', 'relationship_type_id')
      ->addWhere('id', '=', $membershipTypeID)
      ->execute()
      ->first();

    if (empty($membershipType)) {
      \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Not found or inactive. Removing from queue.");
      // Note: The ID is still removed below to prevent infinite loops on fatal logic errors.
    } else {
      // Execute inherited membership rebuild for this Membership Type
      try {
        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Started processing.");
        $this->deleteChildMemberships($membershipTypeID);

        // Check if any Relationships are set for this Membership Type
        if (empty($membershipType['relationship_type_id'])) {
          \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. No Relationship Types set, skipping related memberships update.");
        } else {
          \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Starting related memberships update.");
          $this->updateRelatedMemberships($membershipTypeID);
          \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Completed related memberships update.");
        }
        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Completed processing.");
      }
      catch (\Exception $e) {
        \Civi::log(E::SHORT_NAME)->error("Error processing Membership Type ID: {$membershipTypeID}. " . $e->getMessage());
        // Note: The ID is still removed below to prevent infinite loops on fatal logic errors.
      }
    }

    // Remove the processed (or invalid) ID and update the persistent setting.
    unset($membershipTypesToProcess[$membershipTypeID]);
    \Civi::settings()->set('membershiprelationshiptypeeditor_mtypes_process', $membershipTypesToProcess);

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

      // Create related (inherited) memberships for each of the "owner" memberships.
      foreach ($ownerMemberships as $ownerMembership) {

        $ownerMembershipBAO = new CRM_Member_BAO_Membership();
        $ownerMembershipBAO->id = $ownerMembership['id'];

        $ownerMembershipId = $ownerMembership['id'];

        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeId}. Search for inherited memberships for Owner Membership ID: {$ownerMembershipId}");

        if ($ownerMembershipBAO->find(TRUE)) {
          \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeId}. Create inherited memberships for Owner Membership ID: {$ownerMembershipId}");
          CRM_Member_BAO_Membership::createRelatedMemberships($ownerMembership, $ownerMembershipBAO);
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
