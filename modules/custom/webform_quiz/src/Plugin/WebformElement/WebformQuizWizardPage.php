<?php

namespace Drupal\webform_quiz\Plugin\WebformElement;

use Drupal\image\Entity\ImageStyle;
use Drupal\webform\Plugin\WebformElement\WebformWizardPage;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\file\Entity\File;

/**
 * Provides a 'webform_quiz_wizard_page' element.
 *
 * @WebformElement(
 *   id = "webform_quiz_wizard_page",
 *   label = @Translation("Quiz Wizard page"),
 *   description = @Translation("Provides an element to display multiple form elements as a page in a multi-step form wizard."),
 *   category = @Translation("Wizard"),
 *   hidden = TRUE,
 * )
 */
class WebformQuizWizardPage extends WebformWizardPage {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = [
      'title' => '',
      'open' => FALSE,
      'prev_button_label' => '',
      'next_button_label' => '',
      'bgimage' => '',
      // Attributes.
      'attributes' => [],
      // Submission display.
      'format' => $this->getItemDefaultFormat(),
      'format_html' => '',
      'format_text' => '',
      'format_attributes' => [],
    ] + $this->defineDefaultBaseProperties();
    unset($properties['flex']);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineTranslatableProperties() {
    return array_merge(
      parent::defineTranslatableProperties(),
      ['prev_button_label', 'next_button_label']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isInput(array $element) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isContainer(array $element) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRoot() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function preview() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $build = parent::formatHtmlItem($element, $webform_submission, $options);

    // Add edit page link container to preview.
    // @see Drupal.behaviors.webformWizardPagesLink
    if ($build && isset($options['view_mode']) && $options['view_mode'] === 'preview' && $webform_submission->getWebform()->getSetting('wizard_preview_link')) {
      $build['#children']['wizard_page_link'] = [
        '#type' => 'container',
        '#attributes' => [
          'data-webform-page' => $element['#webform_key'],
          'class' => ['webform-wizard-page-edit'],
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['wizard_page']['bgimage'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Background Image'),
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
        'file_validate_size' => [25600000],
      ],
      '#theme' => 'image_widget',
      '#preview_image_style' => 'medium',
      '#upload_location' => 'public://',
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Generally, elements will not be processing any submitted properties.
    // It is possible that a custom element might need to call a third-party API
    // to 'register' the element.
    $values = $form_state->getValues();

    $bgimage = empty($values['bgimage']) ? NULL : reset($values['bgimage']);
    if (!empty($bgimage)) {

      $bgimage = File::load($bgimage);

      $bgimage->setPermanent();
      $bgimage->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getElementSelectorOptions(array $element) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getElementStateOptions() {
    return [
      'visible' => $this->t('Visible'),
      'invisible' => $this->t('Hidden'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function showPage(array &$element) {
    // When showing a wizard page, page render it as container instead of the
    // default details element.
    // @see \Drupal\webform\Element\WebformWizardPage
    $element['#type'] = 'container';
  }

  /**
   * {@inheritdoc}
   */
  public function hidePage(array &$element) {
    // Set #access to FALSE which will suppresses webform #required validation.
    WebformElementHelper::setPropertyRecursive($element, '#access', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);

    if (!empty($element['#bgimage'])) {
      $file = File::load(reset($element['#bgimage']));
      if ($file) {
        $path = $file->getFileUri();
        $path = ImageStyle::load('large')->buildUrl($file->getFileUri());
        $element['#attributes']['quiz-background'] = $path;
      }
    }
  }

}
