<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Form\CreateFormEnhancerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form hook implementations for Group.
 */
final class FormHooks {

  use StringTranslationTrait;

  public function __construct(
    protected CreateFormEnhancerInterface $createFormEnhancer,
    protected RequestStack $requestStack,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_block_form_alter')]
  public function formBlockFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (isset($form['visibility']['group_type'])) {
      $form['visibility_tabs']['#attached']['library'][] = 'group/block';
      $form['visibility']['group_type']['#title'] = $this->t('Group types');
      $form['visibility']['group_type']['negate']['#type'] = 'value';
      $form['visibility']['group_type']['negate']['#title_display'] = 'invisible';
      $form['visibility']['group_type']['negate']['#value'] = $form['visibility']['group_type']['negate']['#default_value'];
    }
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // GroupRelationshipController::createForm() builds an entity create form
    // for immediately adding a new entity to a group. We need to enhance this
    // form so that the relationship data is properly filled out.
    if ($form_state->has('group__create_form_should_enhance')) {
      $this->createFormEnhancer->enhanceEntityForm($form, $form_state, $form_state->get('group'), $form_state->get('group_relation'));

      $actions = $form['actions'] ?? [];
      foreach (Element::children($actions) as $name) {
        // Remove preview button as it redirects back to the wrong form.
        if ($name == 'preview') {
          unset($form['actions'][$name]);
          continue;
        }

        // Skip buttons without submit handlers.
        if (empty($form['actions'][$name]['#submit'])) {
          continue;
        }

        // Skip buttons that do not properly build and save an entity.
        $submit = $form['actions'][$name]['#submit'];
        if (!in_array('::submitForm', $submit) || !in_array('::save', $submit)) {
          continue;
        }

        $this->createFormEnhancer->enhanceEntityFormSubmit($form['actions'][$name]);
      }
    }
  }

}
