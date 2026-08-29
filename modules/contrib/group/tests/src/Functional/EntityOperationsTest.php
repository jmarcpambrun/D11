<?php

namespace Drupal\Tests\group\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\RoleInterface;

/**
 * Tests that entity operations (do not) show up on the group overview.
 *
 * @see \Drupal\group\Entity\Controller\GroupListBuilder::getDefaultOperations()
 */
#[Group('group')]
#[RunTestsInSeparateProcesses]
class EntityOperationsTest extends GroupBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpAccount();
  }

  /**
   * Checks for entity operations under given circumstances.
   *
   * @param array $visible
   *   A list of visible link labels, keyed by path.
   * @param array $invisible
   *   A list of invisible link labels, keyed by path.
   * @param string[] $permissions
   *   A list of group permissions to assign to the user.
   * @param string[] $modules
   *   A list of modules to enable.
   */
  #[DataProvider('provideEntityOperationScenarios')]
  public function testEntityOperations($visible, $invisible, $permissions = [], $modules = []) {
    $group = $this->createGroup(['type' => $this->createGroupType()->id()]);
    $group->addMember($this->groupCreator);

    if (!empty($permissions)) {
      $this->createGroupRole([
        'group_type' => $group->bundle(),
        'scope' => PermissionScopeInterface::INSIDER_ID,
        'global_role' => RoleInterface::AUTHENTICATED_ID,
        'permissions' => $permissions,
      ]);
    }

    if (!empty($modules)) {
      $this->container->get('module_installer')->install($modules, TRUE);
    }

    $this->drupalGet('admin/group');

    foreach ($visible as $path => $label) {
      $this->assertSession()->linkExists($label);
      $this->assertSession()->linkByHrefExists($path);
    }

    foreach ($invisible as $path => $label) {
      $this->assertSession()->linkNotExists($label);
      $this->assertSession()->linkByHrefNotExists($path);
    }
  }

  /**
   * Data provider for testEntityOperations().
   */
  public static function provideEntityOperationScenarios() {
    $scenarios['withoutAccess'] = [
      [],
      [
        'group/1/edit' => 'Edit',
        'group/1/members' => 'Members',
        'group/1/delete' => 'Delete',
        'group/1/revisions' => 'Revisions',
      ],
    ];

    $scenarios['withAccess'] = [
      [
        'group/1/edit' => 'Edit',
        'group/1/delete' => 'Delete',
        'group/1/revisions' => 'Revisions',
      ],
      [
        'group/1/members' => 'Members',
      ],
      [
        'view group',
        'edit group',
        'delete group',
        'administer members',
        'view group revisions',
      ],
    ];

    $scenarios['withAccessAndViews'] = [
      [
        'group/1/edit' => 'Edit',
        'group/1/members' => 'Members',
        'group/1/delete' => 'Delete',
        'group/1/revisions' => 'Revisions',
      ],
      [],
      [
        'view group',
        'edit group',
        'delete group',
        'administer members',
        'view group revisions',
      ],
      ['views'],
    ];

    return $scenarios;
  }

}
