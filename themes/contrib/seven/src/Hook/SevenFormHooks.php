<?php

declare(strict_types=1);

namespace Drupal\seven\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\views\Form\ViewsForm;

/**
 * Form hook implementations.
 *
 * @internal
 *
 * @see https://www.drupal.org/node/3569107
 */
class SevenFormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new \Drupal\seven\Hook\SevenFormHooks instance.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    TranslationInterface $string_translation,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook("form_alter")]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form_object = $form_state->getFormObject();

    if ($form_object instanceof ViewsForm && !empty($form['header'])) {
      $view = $form_state->getBuildInfo()['args'][0];
      $view_title = $view->getTitle();

      // Determine if the Views form includes a bulk operations form. If it
      // does, move it to the bottom and remove the second bulk operations
      // submit.
      foreach (Element::children($form['header']) as $key) {
        if (str_contains($key, '_bulk_form')) {
          // Move the bulk actions form from the header to its own container.
          $form['bulk_actions_container'] = $form['header'][$key];
          unset($form['header'][$key]);

          // Remove the supplementary bulk operations submit button as it
          // appears in the same location the form was moved to.
          unset($form['actions']);

          $form['bulk_actions_container']['#attributes']['data-drupal-views-bulk-actions'] = '';
          $form['bulk_actions_container']['#attributes']['class'][] = 'views-bulk-actions';
          $form['bulk_actions_container']['actions']['submit']['#button_type'] = 'primary';
          $form['bulk_actions_container']['actions']['submit']['#attributes']['class'][] = 'button--small';
          $label = $this->t('Perform actions on the selected items in the %view_title view', ['%view_title' => $view_title]);
          $label_id = $key . '_group_label';

          // Group the bulk actions select and submit elements; add a label that
          // makes the purpose of these elements clearer to screen readers.
          $form['bulk_actions_container']['#attributes']['role'] = 'group';
          $form['bulk_actions_container']['#attributes']['aria-labelledby'] = $label_id;
          $form['bulk_actions_container']['group_label'] = [
            '#type' => 'container',
            '#markup' => $label,
            '#attributes' => [
              'id' => $label_id,
              'class' => ['visually-hidden'],
            ],
            '#weight' => -1,
          ];

          // Loop through bulk actions items and add the needed CSS classes.
          $bulk_action_item_keys = Element::children($form['bulk_actions_container'], TRUE);
          $bulk_last_key = NULL;
          $bulk_child_before_actions_key = NULL;

          foreach ($bulk_action_item_keys as $bulk_action_item_key) {
            if (!empty($form['bulk_actions_container'][$bulk_action_item_key]['#type'])) {
              if ($form['bulk_actions_container'][$bulk_action_item_key]['#type'] === 'actions') {
                // We need the key of the element that precedes the actions
                // element.
                $bulk_child_before_actions_key = $bulk_last_key;
                $form['bulk_actions_container'][$bulk_action_item_key]['#attributes']['class'][] = 'views-bulk-actions__item';
              }

              if (!in_array($form['bulk_actions_container'][$bulk_action_item_key]['#type'], ['hidden', 'actions'])) {
                $form['bulk_actions_container'][$bulk_action_item_key]['#wrapper_attributes']['class'][] = 'views-bulk-actions__item';
                $bulk_last_key = $bulk_action_item_key;
              }
            }
          }

          if ($bulk_child_before_actions_key) {
            $form['bulk_actions_container'][$bulk_child_before_actions_key]['#wrapper_attributes']['class'][] = 'views-bulk-actions__item--preceding-actions';
          }
        }
      }
    }

    if ($form_id === 'views_exposed_form' && str_starts_with($form['#id'], 'views-exposed-form-media-library-widget')) {
      $form['actions']['#attributes']['class'][] = 'media-library-view--form-actions';
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter().
   */
  #[Hook("form_media_library_add_form_alter")]
  public function mediaLibraryFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#attributes']['class'][] = 'media-library-add-form';
    $form['#attached']['library'][] = 'seven/media_library';

    // If there are unsaved media items, apply styling classes to various parts
    // of the form.
    if (isset($form['media'])) {
      $form['#attributes']['class'][] = 'media-library-add-form--with-input';

      // Put a wrapper around the informational message above the unsaved media
      // items.
      $form['description']['#template'] = '<p class="media-library-add-form__description">{{ text }}</p>';
    }
    else {
      $form['#attributes']['class'][] = 'media-library-add-form--without-input';
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for \Drupal\node\NodeForm.
   *
   * Changes vertical tabs to container.
   */
  #[Hook("form_node_form_alter")]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#theme'] = ['node_edit_form'];
    $form['#attached']['library'][] = 'seven/node-form';

    $form['advanced']['#type'] = 'container';
    $form['meta']['#type'] = 'container';
    $form['meta']['#access'] = TRUE;
    $form['meta']['changed']['#wrapper_attributes']['class'][] = 'container-inline';
    $form['meta']['author']['#wrapper_attributes']['class'][] = 'container-inline';

    $form['revision_information']['#type'] = 'container';
    $form['revision_information']['#group'] = 'meta';
  }

  /**
   * Implements hook_form_FORM_ID_alter() for language_content_settings_form().
   */
  #[Hook("form_language_content_settings_form_alter")]
  public function languageContentSettingsFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#attached']['library'][] = 'seven/layout_builder_content_translation_admin';
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook("form_media_library_add_form_oembed_alter")]
  public function mediaLibraryFormOembedAlter(array &$form, FormStateInterface $form_state): void {
    $form['#attributes']['class'][] = 'media-library-add-form--oembed';

    // If no media items have been added yet, add a couple of styling classes
    // to the initial URL form.
    if (isset($form['container'])) {
      $form['container']['#attributes']['class'][] = 'media-library-add-form__input-wrapper';
      $form['container']['url']['#attributes']['class'][] = 'media-library-add-form-oembed-url';
      $form['container']['submit']['#attributes']['class'][] = 'media-library-add-form-oembed-submit';
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook("form_media_library_add_form_upload_alter")]
  public function mediaLibraryFormUploadAlter(array &$form, FormStateInterface $form_state): void {
    $form['#attributes']['class'][] = 'media-library-add-form--upload';

    if (isset($form['container'])) {
      $form['container']['#attributes']['class'][] = 'media-library-add-form__input-wrapper';
    }
  }

}
