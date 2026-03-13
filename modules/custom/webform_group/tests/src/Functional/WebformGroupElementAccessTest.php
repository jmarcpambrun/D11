<?php

namespace Drupal\Tests\webform_group\Functional;

/**
 * Tests webform group element access.
 *
 * @group webform_group
 */
class WebformGroupElementAccessTest extends WebformGroupBrowserTestBase {

  /**
   * Tests webform group element access.
   */
  public function testGroupElementAccess() {
    $assert = $this->assertSession();

    // Default group.
    $group = $this->createGroup(['type' => 'default']);

    // Webform node.
    $node = $this->createWebformNode('test_group_element_access');

    // Users.
    $outsider_user = $this->createUser();

    $member_user = $this->createUser();
    $group->addMember($member_user);

    $custom_user = $this->createUser();
    $group->addMember($custom_user, ['group_roles' => ['custom']]);

    /* ********************************************************************** */
    // Webform node not related to any group.
    /* ********************************************************************** */

    // Logout.
    $this->drupalLogout();

    // Check that only the anonymous element is displayed.
    $this->drupalGet($node->toUrl());
    $assert->statusCodeEquals(200);
    $assert->fieldExists('anonymous');
    $assert->fieldNotExists('authenticated');
    $assert->fieldNotExists('outsider');
    $assert->fieldNotExists('member');
    $assert->fieldNotExists('custom');

    // Login as an outsider user.
    $this->drupalLogin($outsider_user);

    // Check that only the authenticated element is displayed.
    $this->drupalGet($node->toUrl());
    $assert->statusCodeEquals(200);
    $assert->fieldNotExists('anonymous');
    $assert->fieldExists('authenticated');
    $assert->fieldNotExists('outsider');
    $assert->fieldNotExists('member');
    $assert->fieldNotExists('custom');

    // Login as a member user.
    $this->drupalLogin($member_user);

    // Check that only the authenticated element is displayed.
    $this->drupalGet($node->toUrl());
    $assert->statusCodeEquals(200);
    $assert->fieldNotExists('anonymous');
    $assert->fieldExists('authenticated');
    $assert->fieldNotExists('outsider');
    $assert->fieldNotExists('member');
    $assert->fieldNotExists('custom');

    /* ********************************************************************** */
    // Webform node related to a group.
    /* ********************************************************************** */

    // Add webform node to group.
    $group->addRelationship($node, 'group_node:webform');

    // Logout.
    $this->drupalLogout();

    // Check that only the anonymous element is displayed.
    $this->drupalGet($node->toUrl());
    $assert->statusCodeEquals(200);
    $assert->fieldExists('anonymous');
    $assert->fieldNotExists('authenticated');
    $assert->fieldNotExists('outsider');
    $assert->fieldNotExists('member');
    $assert->fieldNotExists('custom');

    // Login as an outsider user.
    $this->drupalLogin($outsider_user);

    // Check that only the authenticated and outsider element are displayed.
    $this->drupalGet($node->toUrl());
    $assert->statusCodeEquals(200);
    $assert->fieldNotExists('anonymous');
    $assert->fieldExists('authenticated');
    $assert->fieldExists('outsider');
    $assert->fieldNotExists('member');
    $assert->fieldNotExists('custom');

    // Login as a member user.
    $this->drupalLogin($member_user);

    // Check that only the authenticated & custom element is displayed.
    $this->drupalGet($node->toUrl());
    $assert->statusCodeEquals(200);
    $assert->fieldNotExists('anonymous');
    $assert->fieldExists('authenticated');
    $assert->fieldNotExists('outsider');
    $assert->fieldExists('member');
    $assert->fieldNotExists('custom');

    // Login as a custom user.
    $this->drupalLogin($custom_user);

    // Check that only the authenticated and custom element is displayed.
    $this->drupalGet($node->toUrl());
    $assert->statusCodeEquals(200);
    $assert->fieldNotExists('anonymous');
    $assert->fieldExists('authenticated');
    $assert->fieldNotExists('outsider');
    $assert->fieldExists('member');
    $assert->fieldExists('custom');
  }

}
