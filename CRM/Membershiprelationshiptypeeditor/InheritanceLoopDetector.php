<?php

/**
 * Finds membership inheritance loops before CiviCRM core walks into them.
 *
 * Core's CRM_Member_BAO_Membership::createRelatedMemberships() gives an
 * inherited membership to every related contact, then recurses into each
 * inherited membership it saved. Its only guard stops a membership going
 * straight back to the contact it came from (A -> B -> A). Any longer circle,
 * such as A -> B -> C -> A, recurses without end, saving a new inherited
 * membership and a "Membership Signup" activity at every step until the
 * process dies.
 *
 * This walks the graph core would walk, by the same rule: a membership held by
 * contact C, inherited from contact P, passes to every contact related to C
 * except P. It tracks (contact, inherited-from) pairs rather than contacts, so
 * the walk stays finite and reports exactly the circles core would loop on.
 * A contact reached along two different routes is not a loop.
 */
class CRM_Membershiprelationshiptypeeditor_InheritanceLoopDetector {

  /**
   * @var callable
   */
  private $relatedContacts;

  /**
   * Related contact IDs, keyed by contact ID.
   *
   * @var int[][]
   */
  private $related = [];

  /**
   * @param callable $relatedContacts
   *   Given a contact ID, returns the IDs of the contacts that would inherit a
   *   membership that contact holds. Called once per contact; the answers are
   *   reused across findLoop() calls, so use one detector per membership type.
   */
  public function __construct(callable $relatedContacts) {
    $this->relatedContacts = $relatedContacts;
  }

  /**
   * @param int $ownerContactId
   *   The contact holding the owner (primary) membership.
   *
   * @return int[]|null
   *   The contact IDs around the first loop found, starting and ending with the
   *   same contact, or NULL when inheritance from this contact terminates.
   */
  public function findLoop(int $ownerContactId): ?array {
    // Depth-first over (contact, inherited-from) states. Each stack frame is
    // [contact ID, inherited-from contact ID, related contact IDs, next index].
    // The owner inherits from nobody, written as 0.
    $stack = [[$ownerContactId, 0, $this->relatedTo($ownerContactId), 0]];
    $onStack = ["$ownerContactId:0" => 0];
    $finished = [];

    while ($stack) {
      $top = count($stack) - 1;
      [$contactId, $fromContactId, $related, $next] = $stack[$top];

      if ($next >= count($related)) {
        unset($onStack["$contactId:$fromContactId"]);
        $finished["$contactId:$fromContactId"] = TRUE;
        array_pop($stack);
        continue;
      }
      $stack[$top][3]++;

      $relatedContactId = $related[$next];
      if ($relatedContactId === $fromContactId) {
        // Core's own guard: never straight back to where it came from.
        continue;
      }

      $state = "$relatedContactId:$contactId";
      if (isset($onStack[$state])) {
        $loop = array_column(array_slice($stack, $onStack[$state]), 0);
        $loop[] = $relatedContactId;
        return $loop;
      }
      if (isset($finished[$state])) {
        continue;
      }

      $onStack[$state] = count($stack);
      $stack[] = [$relatedContactId, $contactId, $this->relatedTo($relatedContactId), 0];
    }

    return NULL;
  }

  /**
   * @return int[]
   */
  private function relatedTo(int $contactId): array {
    if (!isset($this->related[$contactId])) {
      $this->related[$contactId] = array_values(array_map('intval', ($this->relatedContacts)($contactId)));
    }
    return $this->related[$contactId];
  }

}
