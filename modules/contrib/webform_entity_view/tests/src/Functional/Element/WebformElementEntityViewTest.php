<?php

namespace Drupal\Tests\webform_entity_view\Functional\Element;

use Drupal\Tests\webform\Functional\Element\WebformElementBrowserTestBase;

/**
 * Tests for entity view element.
 *
 * @group webform_entity_view
 */
class WebformElementEntityViewTest extends WebformElementBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['webform_test_entity_view'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['entity_view_test', 'entity_view_test_missing_entity'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->drupalCreateNode(
      [
        'type' => 'entity_view_node_type',
        'field_one' => 'Content of Field One',
        'field_two' => 'Content of Field Two',
      ]
    );
  }

  /**
   * Test entity view element.
   */
  public function testEntityView() {
    $this->drupalGet('/webform/entity_view_test');
    $this->assertSession()->responseContains('Content of Field One');
    $this->assertSession()->responseNotContains('Content of Field Two');
  }

  /**
   * Test entity view element when the entity referenced has been removed.
   */
  public function testEntityViewMissingEntity() {
    $this->drupalGet('/webform/entity_view_test_missing_entity');
    $this->assertSession()->responseNotContains('Content of Field One');
    $this->assertSession()->responseNotContains('Content of Field Two');
  }

}
