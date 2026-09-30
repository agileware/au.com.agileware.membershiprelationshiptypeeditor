<?php

/**
 * Last line of defence against membership inheritance loops.
 *
 * Core recurses into related memberships wherever a membership is saved:
 * renewals, the "Update Membership Statuses" job, relationship edits, imports,
 * not only this extension's job. Checked from hook_civicrm_pre, this refuses to
 * save an inherited membership whose contact already appears further up its own
 * owner_membership_id chain, which no legitimate inheritance produces. The
 * exception ends core's recursion with an error that names the loop, instead
 * of letting it run until the process dies.
 */
class CRM_Membershiprelationshiptypeeditor_InheritanceLoopGuard {

  /**
   * Chains are normally one to three memberships deep. The cap only bounds the
   * walk up a chain that is already corrupt.
   */
  const MAX_DEPTH = 100;

  /**
   * @param array $params
   *   The Membership params passed to hook_civicrm_pre.
   *
   * @throws \CRM_Membershiprelationshiptypeeditor_InheritanceLoopException
   */
  public static function check(array $params): void {
    $contactId = (int) ($params['contact_id'] ?? 0);
    $ownerMembershipId = (int) ($params['owner_membership_id'] ?? 0);
    if (!$contactId || !$ownerMembershipId) {
      return;
    }

    // Contacts up the chain, nearest owner first.
    $chain = [];
    $membershipId = $ownerMembershipId;
    for ($depth = 0; $membershipId && $depth < self::MAX_DEPTH; $depth++) {
      $owner = CRM_Core_DAO::executeQuery(
        'SELECT contact_id, owner_membership_id FROM civicrm_membership WHERE id = %1',
        [1 => [$membershipId, 'Positive']]
      );
      if (!$owner->fetch()) {
        return;
      }

      $chain[] = (int) $owner->contact_id;
      if ((int) $owner->contact_id === $contactId) {
        $path = implode(' -> ', array_merge(array_reverse($chain), [$contactId]));
        throw new CRM_Membershiprelationshiptypeeditor_InheritanceLoopException(
          "Membership inheritance loop: contact {$contactId} would inherit membership {$ownerMembershipId} from itself (contacts {$path}). Change the relationship types on the membership type, or the relationships between these contacts, so the membership cannot return to a contact that already has it.",
          'membership_inheritance_loop',
          ['contact_ids' => array_merge(array_reverse($chain), [$contactId])]
        );
      }
      $membershipId = (int) $owner->owner_membership_id;
    }
  }

}
