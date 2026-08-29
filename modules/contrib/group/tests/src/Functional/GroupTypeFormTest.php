<?php

namespace Drupal\Tests\group\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Depends;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\RoleInterface;

/**
 * Tests the behavior of the group type form.
 */
#[Group('group')]
#[RunTestsInSeparateProcesses]
class GroupTypeFormTest extends GroupBrowserTestBase {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The group type ID to use in testing.
   *
   * @var string
   */
  protected $groupTypeId = 'my_first_group_type';

  /**
   * Contains some common values for the group type form.
   *
   * @var array
   */
  protected $commonValues = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpAccount();

    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->commonValues = [
      'Name' => 'My first group type',
      'id' => $this->groupTypeId,
    ];
  }

  /**
   * Gets the global (site) permissions for the group creator.
   *
   * @return string[]
   *   The permissions.
   */
  protected function getGlobalPermissions() {
    return [
      'administer group',
    ] + parent::getGlobalPermissions();
  }

  /**
   * Sets up and submits the group type add form.
   *
   * This also makes sure the groupCreator account can then create groups.
   *
   * @param array $edit
   *   The group type values to fill out. Will be completed with commonValues.
   */
  protected function createGroupTypeAndAssignCreatePermission(array $edit = []): void {
    $this->drupalGet('/admin/group/types/add');
    $this->assertSession()->statusCodeEquals(200);

    $submit_button = 'Save group type';
    $this->assertSession()->buttonExists($submit_button);
    $this->submitForm($edit + $this->commonValues, $submit_button);

    foreach ($this->groupCreator->getRoles(TRUE) as $role_id) {
      $role = $this->entityTypeManager()->getStorage('user_role')->load($role_id);
      $this->assertInstanceOf(RoleInterface::class, $role);
      $role->grantPermission('create ' . $this->groupTypeId . ' group')->save();
    }
  }

  /**
   * Sets up and submits the group add form.
   *
   * @param array $edit
   *   The group values to fill out.
   * @param bool $creator_membership
   *   Whether the group type was configured to grant a creator membership.
   */
  protected function createGroupViaForm(array $edit, bool $creator_membership = FALSE): void {
    $this->drupalGet('group/add/' . $this->groupTypeId);

    $submit_button = 'Create My first group type';
    if ($creator_membership) {
      $submit_button .= ' and become a member';
    }

    $this->assertSession()->buttonExists($submit_button);
    $this->submitForm($edit, $submit_button);
  }

  /**
   * Tests changing the group title field label.
   */
  public function testCustomGroupTitleFieldLabel() {
    $title_field_label = 'Title for foo';
    $this->createGroupTypeAndAssignCreatePermission(['Title field label' => $title_field_label]);

    $this->drupalGet('group/add/' . $this->groupTypeId);
    $this->assertSession()->pageTextContains($title_field_label);
  }

  /**
   * Tests not granting the group creator a membership.
   */
  public function testNoCreatorMembership() {
    $this->createGroupTypeAndAssignCreatePermission(['The group creator automatically becomes a member' => 0]);
    $this->createGroupViaForm(['label[0][value]' => 'Foo']);

    $group = $this->entityTypeManager()->getStorage('group')->load(1);
    $this->assertEquals($this->groupCreator->id(), $group->getOwnerId());
    $this->assertFalse($group->getMember($this->groupCreator));
  }

  /**
   * Tests granting the group creator a membership.
   */
  public function testCreatorMembership() {
    $this->createGroupTypeAndAssignCreatePermission(['The group creator automatically becomes a member' => 1]);
    $this->createGroupViaForm(['label[0][value]' => 'Foo']);

    $group = $this->entityTypeManager()->getStorage('group')->load(1);
    $this->assertEquals($this->groupCreator->id(), $group->getOwnerId());
    $this->assertInstanceOf(GroupMembership::class, $group->getMember($this->groupCreator));
  }

  /**
   * Tests granting the group creator a membership with roles.
   *
   * @depends testCreatorMembership
   */
  #[Depends('testCreatorMembership')]
  public function testCreatorMembershipRoles() {
    $this->createGroupTypeAndAssignCreatePermission(['The group creator automatically becomes a member' => 1]);

    // Create a group role we can set as a creator role.
    $group_role = $this->createGroupRole([
      'group_type' => $this->groupTypeId,
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
    ]);

    // Update the group type.
    $this->drupalGet('/admin/group/types/manage/' . $this->groupTypeId);
    $submit_button = 'Save group type';
    $this->assertSession()->buttonExists($submit_button);
    $this->submitForm(['creator_roles[' . $group_role->id() . ']' => TRUE], $submit_button);

    // Now create a group and check the creator roles.
    $this->createGroupViaForm(['label[0][value]' => 'Foo']);
    $group = $this->entityTypeManager()->getStorage('group')->load(1);
    $this->assertEquals($this->groupCreator->id(), $group->getOwnerId());

    // Check that the roles assigned to the created member are the same as what
    // we configured in the group defaults.
    $member = $group->getMember($this->groupCreator);
    $this->assertInstanceOf(GroupMembership::class, $member);
    $ids = array_column($member->get('group_roles')->getValue(), 'target_id');
    $this->assertEquals([$group_role->id()], $ids, 'Set the correct creator roles.');
  }

  /**
   * Tests not creating the default roles.
   */
  public function testNoCreateDefaultRoles() {
    $this->createGroupTypeAndAssignCreatePermission(['Automatically configure useful default roles' => 0]);

    $storage = $this->entityTypeManager->getStorage('group_role');
    $this->assertNull($storage->load($this->groupTypeId . '-anonymous'));
    $this->assertNull($storage->load($this->groupTypeId . '-outsider'));
    $this->assertNull($storage->load($this->groupTypeId . '-member'));
  }

  /**
   * Tests creating the default roles.
   */
  public function testCreateDefaultRoles() {
    $this->createGroupTypeAndAssignCreatePermission(['Automatically configure useful default roles' => 1]);

    $storage = $this->entityTypeManager->getStorage('group_role');
    $this->assertNotNull($storage->load($this->groupTypeId . '-anonymous'));
    $this->assertNotNull($storage->load($this->groupTypeId . '-outsider'));
    $this->assertNotNull($storage->load($this->groupTypeId . '-member'));
  }

  /**
   * Tests not creating the admin role.
   */
  public function testNoCreateAdminRole() {
    $this->createGroupTypeAndAssignCreatePermission(['Automatically configure an administrative role' => 0]);

    $storage = $this->entityTypeManager->getStorage('group_role');
    $this->assertNull($storage->load($this->groupTypeId . '-admin'));
  }

  /**
   * Tests creating the admin role.
   */
  public function testCreateAdminRole() {
    $this->createGroupTypeAndAssignCreatePermission(['Automatically configure an administrative role' => 1]);

    $storage = $this->entityTypeManager->getStorage('group_role');
    $this->assertNotNull($storage->load($this->groupTypeId . '-admin'));
  }

  /**
   * Tests not assigning the admin role.
   *
   * @depends testCreatorMembership
   * @depends testCreateAdminRole
   */
  #[Depends('testCreatorMembership')]
  #[Depends('testCreateAdminRole')]
  public function testNoAssignAdminRole() {
    $edit = [
      'The group creator automatically becomes a member' => 1,
      'Automatically configure an administrative role' => 1,
      'Automatically assign this administrative role to group creators' => 0,
    ];
    $this->createGroupTypeAndAssignCreatePermission($edit);
    $this->createGroupViaForm(['label[0][value]' => 'Foo']);

    $group = $this->entityTypeManager()->getStorage('group')->load(1);
    $membership = $group->getMember($this->groupCreator);
    $ids = array_column($membership->get('group_roles')->getValue(), 'target_id');
    $this->assertNotContains($this->groupTypeId . '-admin', $ids);
  }

  /**
   * Tests assigning the admin role.
   *
   * @depends testCreatorMembership
   * @depends testCreateAdminRole
   */
  #[Depends('testCreatorMembership')]
  #[Depends('testCreateAdminRole')]
  public function testAssignAdminRole() {
    $edit = [
      'The group creator automatically becomes a member' => 1,
      'Automatically configure an administrative role' => 1,
      'Automatically assign this administrative role to group creators' => 1,
    ];
    $this->createGroupTypeAndAssignCreatePermission($edit);
    $this->createGroupViaForm(['label[0][value]' => 'Foo']);
    $this->assertSession()->addressEquals('group/1');

    $group = $this->entityTypeManager()->getStorage('group')->load(1);
    $membership = $group->getMember($this->groupCreator);
    $ids = array_column($membership->get('group_roles')->getValue(), 'target_id');
    $this->assertContains($this->groupTypeId . '-admin', $ids);
  }

  /**
   * Tests that the absence of a global admin role makes options disappear.
   */
  public function testGlobalAdminRoleDetection() {
    $this->drupalGet('/admin/group/types/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('We have detected that your site has an all-access global role called');
  }

  /**
   * Tests that the presence of a global admin role makes options show up.
   */
  public function testNoGlobalAdminRoleDetection() {
    $this->createAdminRole();
    $this->drupalGet('/admin/group/types/add');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('We have detected that your site has an all-access global role called');
  }

  /**
   * Tests not creating the admin outsider role.
   *
   * @depends testGlobalAdminRoleDetection
   */
  #[Depends('testGlobalAdminRoleDetection')]
  public function testNoOutsiderAdminRoleCreation() {
    $this->createAdminRole();
    $this->createGroupTypeAndAssignCreatePermission(['add_admin_outsider' => 0]);

    $storage = $this->entityTypeManager->getStorage('group_role');
    $this->assertNull($storage->load($this->groupTypeId . '-admin_out'));
  }

  /**
   * Tests creating the admin outsider role.
   *
   * @depends testGlobalAdminRoleDetection
   */
  #[Depends('testGlobalAdminRoleDetection')]
  public function testOutsiderAdminRoleCreation() {
    $this->createAdminRole();
    $this->createGroupTypeAndAssignCreatePermission(['add_admin_outsider' => 1]);

    $storage = $this->entityTypeManager->getStorage('group_role');
    $this->assertNotNull($storage->load($this->groupTypeId . '-admin_out'));
  }

  /**
   * Tests not creating the admin insider role.
   *
   * @depends testGlobalAdminRoleDetection
   */
  #[Depends('testGlobalAdminRoleDetection')]
  public function testNoInsiderAdminRoleCreation() {
    $this->createAdminRole();
    $this->createGroupTypeAndAssignCreatePermission(['add_admin_insider' => 0]);

    $storage = $this->entityTypeManager->getStorage('group_role');
    $this->assertNull($storage->load($this->groupTypeId . '-admin_in'));
  }

  /**
   * Tests creating the admin insider role.
   *
   * @depends testGlobalAdminRoleDetection
   */
  #[Depends('testGlobalAdminRoleDetection')]
  public function testInsiderAdminRoleCreation() {
    $this->createAdminRole();
    $this->createGroupTypeAndAssignCreatePermission(['add_admin_insider' => 1]);

    $storage = $this->entityTypeManager->getStorage('group_role');
    $this->assertNotNull($storage->load($this->groupTypeId . '-admin_in'));
  }

}
