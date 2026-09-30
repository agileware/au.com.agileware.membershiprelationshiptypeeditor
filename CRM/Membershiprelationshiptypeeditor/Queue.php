<?php

/**
 * The queue of membership types waiting for their inherited memberships to be
 * rebuilt.
 *
 * Stored in the "membershiprelationshiptypeeditor_mtypes_process" setting as
 * [membership_type_id => TRUE], in processing order.
 */
class CRM_Membershiprelationshiptypeeditor_Queue {

  const SETTING = 'membershiprelationshiptypeeditor_mtypes_process';

  /**
   * @return bool[]
   *   TRUE keyed by membership type ID, in processing order.
   */
  public static function get(): array {
    $stored = \Civi::settings()->get(self::SETTING);
    if (!is_array($stored)) {
      return [];
    }

    $queue = [];
    foreach ($stored as $membershipTypeId => $queued) {
      if ((int) $membershipTypeId > 0 && $queued !== FALSE) {
        $queue[(int) $membershipTypeId] = TRUE;
      }
    }
    return $queue;
  }

  /**
   * Add membership types to the back of the queue. Types already queued keep
   * their place.
   *
   * @param int[] $membershipTypeIds
   */
  public static function add(array $membershipTypeIds): void {
    $queue = self::get();
    foreach ($membershipTypeIds as $membershipTypeId) {
      $membershipTypeId = (int) $membershipTypeId;
      if ($membershipTypeId > 0 && !isset($queue[$membershipTypeId])) {
        $queue[$membershipTypeId] = TRUE;
      }
    }
    self::save($queue);
  }

  /**
   * Remove a membership type from the queue.
   */
  public static function remove(int $membershipTypeId): void {
    $queue = self::get();
    unset($queue[$membershipTypeId]);
    self::save($queue);
  }

  private static function save(array $queue): void {
    \Civi::settings()->set(self::SETTING, $queue);
  }

}
