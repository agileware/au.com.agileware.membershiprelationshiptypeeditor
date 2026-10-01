<?php

use Civi\Api4\Membership;
use Civi\Api4\MembershipType;
use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

class CRM_Membershiprelationshiptypeeditor_MembershipType {

  /**
   * Rebuilds the inherited memberships of the next queued membership type.
   *
   * @return array
   *   - processed: the membership type ID, or NULL if nothing was processed.
   *   - skipped_owner_memberships: owner membership IDs left alone because
   *     their inheritance loops.
   *   - failed_owner_memberships: owner membership IDs that raised an error.
   */
  public function process(): array {
    $result = [
      'processed' => NULL,
      'skipped_owner_memberships' => [],
      'failed_owner_memberships' => [],
    ];

    // The job manager does not stop two runs overlapping (cron plus a manual
    // run, say); both would rebuild the same memberships at once.
    $lock = \Civi::lockManager()->acquire('worker.membershiprelationshiptypeeditor');
    if (!$lock->isAcquired()) {
      \Civi::log(E::SHORT_NAME)->info('Another run is still processing the queue. Skipping this run.');
      return $result;
    }

    try {
      $membershipTypeID = CRM_Membershiprelationshiptypeeditor_Queue::claimNext();
      if (!$membershipTypeID) {
        return $result;
      }
      $result['processed'] = $membershipTypeID;

      $membershipType = MembershipType::get(FALSE)
        ->addSelect('id', 'relationship_type_id')
        ->addWhere('id', '=', $membershipTypeID)
        ->execute()
        ->first();

      if (empty($membershipType)) {
        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Not found. Removing from queue.");
        CRM_Membershiprelationshiptypeeditor_Queue::remove($membershipTypeID);
        return $result;
      }

      try {
        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Started processing.");
        $this->deleteChildMemberships($membershipTypeID);

        if (empty($membershipType['relationship_type_id'])) {
          \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. No Relationship Types set, skipping related memberships update.");
        }
        else {
          \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Starting related memberships update.");
          $result = array_merge($result, $this->updateRelatedMemberships($membershipTypeID));
          \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Completed related memberships update. Owner memberships skipped for loops: " . count($result['skipped_owner_memberships']) . ', failed: ' . count($result['failed_owner_memberships']) . '.');
        }

        CRM_Membershiprelationshiptypeeditor_Queue::remove($membershipTypeID);
        \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeID}. Completed processing.");
      }
      catch (\Throwable $e) {
        // Left in the queue: claimNext() retries it, up to Queue::MAX_ATTEMPTS.
        \Civi::log(E::SHORT_NAME)->error("Error processing Membership Type ID: {$membershipTypeID}. " . $e->getMessage());
      }
    }
    finally {
      $lock->release();
    }

    return $result;
  }

  /**
   * Create inherited memberships for every owner membership of a type.
   *
   * @param int $membershipTypeId
   *
   * @return array
   *   skipped_owner_memberships and failed_owner_memberships, as for process().
   * @throws \CRM_Core_Exception
   */
  private function updateRelatedMemberships(int $membershipTypeId): array {
    $skipped = $failed = [];

    $ownerMemberships = Membership::get(FALSE)
      ->addWhere('owner_membership_id', 'IS NULL')
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->execute();

    \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeId}. Retrieved all the Owner Memberships.");

    // The same relationship lookup core uses when it passes memberships on.
    $loopDetector = new CRM_Membershiprelationshiptypeeditor_InheritanceLoopDetector(
      fn(int $contactId) => array_keys(CRM_Member_BAO_Membership::checkMembershipRelationship($membershipTypeId, $contactId))
    );

    foreach ($ownerMemberships as $ownerMembership) {
      $ownerMembershipId = $ownerMembership['id'];

      $loop = $loopDetector->findLoop((int) $ownerMembership['contact_id']);
      if ($loop) {
        $skipped[] = $ownerMembershipId;
        \Civi::log(E::SHORT_NAME)->error("Membership Type ID: {$membershipTypeId}. Skipped Owner Membership ID: {$ownerMembershipId}: inheritance loops through contacts " . implode(' -> ', $loop) . '. Change the relationship types on the membership type, or the relationships between these contacts, so the membership cannot return to a contact that already has it.');
        continue;
      }

      try {
        $ownerMembershipBAO = new CRM_Member_BAO_Membership();
        $ownerMembershipBAO->id = $ownerMembershipId;
        if ($ownerMembershipBAO->find(TRUE)) {
          \Civi::log(E::SHORT_NAME)->info("Membership Type ID: {$membershipTypeId}. Create inherited memberships for Owner Membership ID: {$ownerMembershipId}");
          CRM_Member_BAO_Membership::createRelatedMemberships($ownerMembership, $ownerMembershipBAO);
        }
      }
      catch (CRM_Membershiprelationshiptypeeditor_InheritanceLoopException $e) {
        // A loop the detector missed, stopped by the pre hook guard.
        $skipped[] = $ownerMembershipId;
        \Civi::log(E::SHORT_NAME)->error("Membership Type ID: {$membershipTypeId}. Skipped Owner Membership ID: {$ownerMembershipId}: " . $e->getMessage());
      }
      catch (\Throwable $e) {
        $failed[] = $ownerMembershipId;
        \Civi::log(E::SHORT_NAME)->error("Membership Type ID: {$membershipTypeId}. Error for Owner Membership ID: {$ownerMembershipId}: " . $e->getMessage());
      }
    }

    return [
      'skipped_owner_memberships' => $skipped,
      'failed_owner_memberships' => $failed,
    ];
  }

  /**
   * Delete inherited memberships of a type whose contact is no longer related
   * to the owner by a current relationship.
   *
   * @param int $membershipTypeId
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function deleteChildMemberships(int $membershipTypeId): void {
    // Every membership of this type that others inherit from.
    $ownerMembershipIds = Membership::get(FALSE)
      ->addSelect('owner_membership_id')
      ->addWhere('owner_membership_id', 'IS NOT NULL')
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->addGroupBy('owner_membership_id')
      ->execute()
      ->column('owner_membership_id');
    if (!$ownerMembershipIds) {
      return;
    }

    $ownerMemberships = Membership::get(FALSE)
      ->addSelect('id', 'contact_id')
      ->addWhere('id', 'IN', $ownerMembershipIds)
      ->execute();

    foreach ($ownerMemberships as $ownerMembership) {
      // Contacts related to the owner by a current relationship.
      $related = CRM_Member_BAO_Membership::checkMembershipRelationship($membershipTypeId, $ownerMembership['contact_id']);

      $deleteAction = Membership::delete(FALSE)
        ->addWhere('owner_membership_id', '=', $ownerMembership['id']);
      if ($related) {
        $deleteAction->addWhere('contact_id', 'NOT IN', array_keys($related));
      }
      $deleteAction->execute();
    }
  }

}
