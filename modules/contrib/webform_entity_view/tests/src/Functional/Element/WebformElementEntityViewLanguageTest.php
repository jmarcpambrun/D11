<?php

namespace Drupal\Tests\webform_entity_view\Functional\Element;

use Drupal\Tests\webform\Functional\Element\WebformElementBrowserTestBase;


/**
 * Tests for entity view element.
 *
 * @group webform_entity_view
 */
class WebformElementEntityViewLanguageTest extends WebformElementBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['webform_test_entity_view', 'webform_ui', 'webform_entity_view', 'config_translation', 'content_translation', 'language', 'webform', 'user'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['entity_view_test_translation'];

  /**
   * The default language code to use in this test.
   *
   * @var array
   */
  protected $defaultLangcode = 'en';

  /**
   * Languages to enable.
   *
   * @var array
   */
  protected $additionalLangcodes = ['es'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);

    $this->loadWebforms(static::$testWebforms);

    // Create one node.
    $this->drupalCreateNode(
      [
        'type' => 'entity_view_node_type',
        'title' => 'Entity test',
      ]
    );
  }

  /**
   * Test entity view element.
   */
  public function testEntityView() {

    // Check the node loads correctly.
    $test_node = $this->drupalGetNodeByTitle('Entity test');
    $this->drupalGet('/node/' . $test_node->id());
    $this->assertSession()->responseContains('Entity test');

    // Check the webform loads correctly.
    $this->drupalGet('/webform/entity_view_test_translation');
    $this->assertSession()->responseContains('Entity test');

    // Install the Spanish language.
    $edit = [];
    $edit['predefined_langcode'] = 'es';
    $this->drupalGet('admin/config/regional/language/add');
    $this->submitForm($edit, 'Add language');

     // Enable translation for entity_view_node_type.
    $edit = [
      'entity_types[node]' => TRUE,
      'settings[node][entity_view_node_type][translatable]' => TRUE,
      'settings[node][entity_view_node_type][settings][language][language_alterable]' => TRUE,
    ];
    $this->drupalGet('admin/config/regional/content-language');
    $this->submitForm($edit, 'Save configuration');

    // Translate content into Spanish.
    $edit = [];
    $edit['title[0][value]'] = 'Elemento de test de tipo entity view';
    $this->drupalGet('/node/' . $test_node->id() . '/translations/add/en/es');
    $this->submitForm($edit, 'Save (this translation)');

    // Check that the translation was successful.
    $this->drupalGet('/es/node/' . $test_node->id());
    $this->assertSession()->responseContains('Elemento de test de tipo entity view');

    // Translate webform into Spanish.
    $edit = [];
    $edit['Title'] = 'Formulario de prueba';
    $this->drupalGet('admin/structure/webform/manage/entity_view_test_translation/translate/es/add');
    $this->assertSession()->responseContains('Save translation');
    $this->submitForm($edit, 'Save translation');

    // Check that the translation was successful.
    $this->drupalGet('/es/webform/entity_view_test_translation');
    $this->assertSession()->responseContains('Formulario de prueba');

    // Check that the entity view element is displayed in Spanish in the webform.
    $this->assertSession()->responseContains('Elemento de test de tipo entity view');
    $this->assertSession()->responseNotContains('Entity test');


  }

}


