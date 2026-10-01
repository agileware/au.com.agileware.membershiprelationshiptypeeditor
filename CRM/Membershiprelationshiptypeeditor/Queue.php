<?php

use CRM_Membershiprelationshiptypeeditor_ExtensionUtil as E;

/**
 * The queue of membership types waiting for their inherited memberships to be
 * rebuilt.
 *
 * Stored in the "membershiprelationshiptypeeditor_mtypes_process" setting as
 * [membership_type_id => attempts], in processing order. Versions before 1.7
 * stored [membership_type_id => TRUE]; those entries read as zero attempts.
 */
class CRM_Membershiprelationshiptypeeditor_Queue {

  const SETTING = 'membershiprelationshiptypeeditor_mtypes_process';

  /**
   * A membership type claimed this many times without completing is dropped,
   * so one type that keeps failing cannot hold up the rest of the queue.
   */
  const MAX_ATTEMPTS = 3;

  /**
   * @return int[]
   *   Attempts so far, keyed by membership type ID, in processing order.
   */
  public static function get(): array {
    $stored = \Civi::settings()->get(self::SETTING);
    if (!is_array($stored)) {
      return [];
    }

    $queue = [];
    foreach ($stored as $membershipTypeId => $attempts) {
      if ((int) $membershipTypeId > 0 && $attempts !== FALSE) {
        $queue[(int) $membershipTypeId] = is_int($attempts) ? $attempts : 0;
      }
    }
    return $queue;
  }

  /**
   * Add membership types to the back of the queue. Types already queued keep
   * their place and their attempt count.
   *
   * @param int[] $membershipTypeIds
   */
  public static function add(array $membershipTypeIds): void {
    $queue = self::get();
    foreach ($membershipTypeIds as $membershipTypeId) {
      $membershipTypeId = (int) $membershipTypeId;
      if ($membershipTypeId > 0 && !isset($queue[$membershipTypeId])) {
        $queue[$membershipTypeId] = 0;
      }
    }
    self::save($queue);
  }

  /**
   * Make the queue exactly these membership types. Types that stay queued keep
   * their place and their attempt count.
   *
   * @param int[] $membershipTypeIds
   */
  public static function replace(array $membershipTypeIds): void {
    $keep = array_fill_keys(array_map('intval', $membershipTypeIds), TRUE);
    self::save(array_intersect_key(self::get(), $keep));
    self::add($membershipTypeIds);
  }

  /**
   * Take the next membership type to process.
   *
   * The type moves to the back of the queue, with its attempt count raised,
   * before any work starts. A run that dies part-way (a timeout or fatal
   * error, which no catch block sees) therefore cannot leave it stuck at the
   * front, and it is retried only after the other queued types have had a
   * turn.
   *
   * @return int|null
   *   The membership type ID, or NULL when the queue is empty.
   */
  public static function claimNext(): ?int {
    $queue = self::get();
    while ($queue) {
      $membershipTypeId = array_key_first($queue);
      $attempts = $queue[$membershipTypeId] + 1;
      unset($queue[$membershipTypeId]);

      if ($attempts > self::MAX_ATTEMPTS) {
        \Civi::log(E::SHORT_NAME)->error("Membership Type ID: {$membershipTypeId}. Removed from the queue after " . self::MAX_ATTEMPTS . ' attempts that did not complete.');
        continue;
      }

      $queue[$membershipTypeId] = $attempts;
      self::save($queue);
      return $membershipTypeId;
    }

    self::save($queue);
    return NULL;
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
