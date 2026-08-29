<?php

namespace Drupal\Tests\group\Kernel\Views;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the group_relationship_to_entity_reverse relationship handler.
 *
 * @see \Drupal\group\Plugin\views\relationship\GroupRelationshipToEntityReverse
 */
#[Group('group')]
#[RunTestsInSeparateProcesses]
class GroupRelationshipToEntityReverseRelationshipTest extends GroupRelationshipToEntityRelationshipTest {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_group_relationship_to_entity_reverse_relationship'];

}
