<?php

/**
 * The detector is plain PHP: each test describes who would inherit from whom
 * as a map of contact ID => related contact IDs.
 */
class CRM_Membershiprelationshiptypeeditor_InheritanceLoopDetectorTest extends \PHPUnit\Framework\TestCase {

  private function detector(array $graph): CRM_Membershiprelationshiptypeeditor_InheritanceLoopDetector {
    return new CRM_Membershiprelationshiptypeeditor_InheritanceLoopDetector(fn(int $contactId) => $graph[$contactId] ?? []);
  }

  /**
   * Asserts $loop is a closed walk over edges of $graph that core would take.
   */
  private function assertLoopIn(array $graph, ?array $loop): void {
    $this->assertNotNull($loop, 'Expected a loop');
    $this->assertGreaterThanOrEqual(4, count($loop), 'A loop core cannot stop has at least three contacts');
    $this->assertSame($loop[0], end($loop), 'A loop starts and ends with the same contact');
    for ($i = 1; $i < count($loop); $i++) {
      $this->assertContains($loop[$i], $graph[$loop[$i - 1]] ?? [], "{$loop[$i - 1]} does not pass memberships to {$loop[$i]}");
    }
  }

  public function testOrganisationWithEmployeesDoesNotLoop(): void {
    $graph = [1 => [2, 3, 4]];
    $this->assertNull($this->detector($graph)->findLoop(1));
  }

  /**
   * Anglicare WA (1) passes to Naomi (2), Mark (3) and Karen (4); Mark passes
   * to his EA Karen; Karen, as a two-way "Member Contact", passes back to 1.
   */
  public function testThreeContactCircleLoops(): void {
    $graph = [1 => [2, 3, 4], 3 => [4], 4 => [1]];
    $this->assertLoopIn($graph, $this->detector($graph)->findLoop(1));
  }

  /**
   * The same contacts once "Member Contact" only passes from the organisation
   * to the person.
   */
  public function testThreeContactsWithoutCircleDoNotLoop(): void {
    $graph = [1 => [2, 3, 4], 3 => [4]];
    $this->assertNull($this->detector($graph)->findLoop(1));
  }

  /**
   * Core never passes a membership straight back where it came from.
   */
  public function testStraightBackIsNotALoop(): void {
    $graph = [1 => [2], 2 => [1]];
    $this->assertNull($this->detector($graph)->findLoop(1));
  }

  /**
   * One contact inheriting along two routes is duplication, not a loop.
   */
  public function testTwoRoutesToOneContactDoNotLoop(): void {
    $graph = [1 => [2, 3], 2 => [4], 3 => [4]];
    $this->assertNull($this->detector($graph)->findLoop(1));
  }

  /**
   * 1 reaches 3 directly first, and 3 -> 1 is "straight back" on that route.
   * Via 2, 3 -> 1 is not, and core loops 1 -> 2 -> 3 -> 1 -> ... forever.
   * A check on contacts alone would mark 3 as explored and miss this.
   */
  public function testLoopReachedByASecondRouteIsFound(): void {
    $graph = [1 => [3, 2], 2 => [3], 3 => [1]];
    $this->assertLoopIn($graph, $this->detector($graph)->findLoop(1));
  }

  public function testLoopNotThroughTheOwnerIsFound(): void {
    $graph = [1 => [2], 2 => [3], 3 => [4], 4 => [2]];
    $this->assertLoopIn($graph, $this->detector($graph)->findLoop(1));
  }

  public function testRelatedContactsAreLookedUpOncePerContact(): void {
    $lookups = [];
    $graph = [1 => [2, 3], 2 => [4], 3 => [4], 5 => [2]];
    $detector = new CRM_Membershiprelationshiptypeeditor_InheritanceLoopDetector(function (int $contactId) use ($graph, &$lookups) {
      $lookups[] = $contactId;
      return $graph[$contactId] ?? [];
    });

    $detector->findLoop(1);
    $detector->findLoop(5);

    sort($lookups);
    $this->assertSame([1, 2, 3, 4, 5], $lookups);
  }

}
