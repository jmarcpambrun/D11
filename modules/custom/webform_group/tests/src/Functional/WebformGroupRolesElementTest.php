<?php

namespace Drupal\Tests\webform_group\Functional;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform_group\Element\WebformGroupRoles;

/**
 * Tests webform group roles element.
 *
 * @group webform_group
 */
class WebformGroupRolesElementTest extends WebformGroupBrowserTestBase {

  /**
   * Tests webform group roles element.
   */
  public function testGroupRolesElement() {
    $assert = $this->assertSession();

    $webform = Webform::load('test_element_group_roles');

    /* ********************************************************************** */

    // Check default element properties.
    $element = [];
    $options = WebformGroupRoles::getGroupRolesOptions($element);
    WebformElementHelper::convertRenderMarkupToStrings($options);
    $this->assertEquals([
      'Group role types' => [
        'member' => 'Member',
        'custom' => 'Custom',
        'authenticated' => 'Authenticated',
      ],
      'Default label' => [
        'authenticated' => 'Default label: Authenticated',
        'member' => 'Default label: Member',
        'custom' => 'Default label: Custom',
      ],
      'Other label' => [
        'other-authenticated' => 'Other label: Authenticated',
        'other-member' => 'Other label: Member',
      ],
    ], $options);

    // Check custom element properties.
    $element = [
      '#include_internal' => FALSE,
      '#include_user_roles' => TRUE,
      '#include_anonymous' => TRUE,
    ];
    $options = WebformGroupRoles::getGroupRolesOptions($element);
    WebformElementHelper::convertRenderMarkupToStrings($options);
    $this->assertEquals([
      'Group role types' => [
        'member' => 'Member',
      ],
      'Default label' => [
        'member' => 'Default label: Member',
      ],
      'Other label' => [
        'other-member' => 'Other label: Member',
      ],
    ], $options);

    // Check posting group role.
    $edit = [
      'webform_group_roles' => ['custom', 'member'],
      'webform_group_roles_advanced' => 'other-member',
    ];
    $this->postSubmission($webform, $edit);
    $assert->responseContains('webform_group_roles:
  - custom
  - member
webform_group_roles_advanced: other-member');
  }

}
