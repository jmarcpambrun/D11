<?php

namespace Drupal\Tests\webform_iban_field\Functional\Element;

use Drupal\Tests\webform\Functional\Element\WebformElementBrowserTestBase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

// cspell:ignore lpln bngh
/**
 * Tests for webform_iban_field.
 *
 * @group Webform
 */
class WebformIbanFieldTest extends WebformElementBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['webform_iban_field'];

  /**
   * Tests IBAN field.
   */
  public function testWebformIbanField(): void {
    $webform = Webform::load('webform_iban_field');

    // Check form element rendering.
    $this->drupalGet('webform/webform_iban_field');

    $this->assertSession()->fieldExists('webform_iban_field');
    $this->assertSession()->fieldExists('webform_iban_field_multiple[items][0][_item_]');
    $this->assertSession()->elementExists('css', '.form-text.webform-iban-field');

    // Single field Submission fails.
    $edit = [
      'webform_iban_field' => '{Test}',
      'webform_iban_field_multiple[items][0][_item_]' => '{Test 01}',
    ];
    $sid = $this->postSubmission($webform, $edit);
    $this->assertEquals(NULL, $sid);

    // Multiple field Submission fails.
    $edit = [
      'webform_iban_field_multiple[items][0][_item_]' => '{Test 01}',
    ];
    $sid = $this->postSubmission($webform, $edit);
    $this->assertEquals(NULL, $sid);

    // Value 0, Submission fails.
    $edit = [
      'webform_iban_field' => '0',
    ];
    $sid = $this->postSubmission($webform, $edit);
    $this->assertEquals(NULL, $sid);

    // Submission succeeds.
    $edit = [
      'webform_iban_field' => 'NL78LPLN0822253585',
      'webform_iban_field_multiple[items][0][_item_]' => 'NL52BNGH0374594309',
    ];
    $sid = $this->postSubmission($webform, $edit);
    $webform_submission = WebformSubmission::load($sid);
    $this->assertEquals('NL78LPLN0822253585', $webform_submission->getElementData('webform_iban_field'));
    $this->assertEquals(['NL52BNGH0374594309'], $webform_submission->getElementData('webform_iban_field_multiple'));
  }

}
