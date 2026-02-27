<?php

namespace Drupal\Tests\webform_group\Functional;

use Drupal\Tests\group\Functional\GroupBrowserTestBase;
use Drupal\Tests\webform\Traits\WebformBrowserTestTrait;
use Drupal\Tests\webform_node\Traits\WebformNodeBrowserTestTrait;
use Drupal\group\PermissionScopeInterface;
use Drupal\user\RoleInterface;

/**
 * Base class for webform group tests.
 */
abstract class WebformGroupBrowserTestBase extends GroupBrowserTestBase {

  use WebformBrowserTestTrait;
  use WebformNodeBrowserTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['webform_group', 'webform_group_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpAccount();
    $node_permission_provider = $this->container->get('group_relation_type.manager')->getPermissionProvider('group_node:webform');

    $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'anonymous',
      'label' => 'Anonymous',
      'group_type' => 'default',
      'scope' => PermissionScopeInterface::OUTSIDER_ID,
      'global_role' => RoleInterface::ANONYMOUS_ID,
      'permissions' => [
        $node_permission_provider->getPermission('view', 'entity',),
        $node_permission_provider->getPermission('view', 'relationship',),
      ],
    ])->save();
    $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'authenticated',
      'label' => 'Authenticated',
      'group_type' => 'default',
      'scope' => PermissionScopeInterface::OUTSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => [
        $node_permission_provider->getPermission('view', 'entity',),
        $node_permission_provider->getPermission('view', 'relationship',),
      ],
    ])->save();
    $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'member',
      'label' => 'Member',
      'group_type' => 'default',
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => [
        $node_permission_provider->getPermission('view', 'entity',),
        $node_permission_provider->getPermission('view', 'relationship',),
      ],
    ])->save();
    $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'custom',
      'label' => 'Custom',
      'group_type' => 'default',
      'scope' => PermissionScopeInterface::INDIVIDUAL_ID,
      'permissions' => [
        $node_permission_provider->getPermission('view', 'entity',),
        $node_permission_provider->getPermission('view', 'relationship',),
      ],
    ])->save();
    $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'other-member',
      'label' => 'Member',
      'group_type' => 'other',
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => [
        $node_permission_provider->getPermission('view', 'entity',),
        $node_permission_provider->getPermission('view', 'relationship',),
      ],
    ])->save();
    $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'other-authenticated',
      'label' => 'Authenticated',
      'group_type' => 'other',
      'scope' => PermissionScopeInterface::OUTSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => [
        $node_permission_provider->getPermission('view', 'entity',),
        $node_permission_provider->getPermission('view', 'relationship',),
      ],
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->purgeSubmissions();
    parent::tearDown();
  }

}
