<?php

namespace Drupal\book\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;

/**
 * Configure book settings for this site.
 *
 * @internal
 */
class BookSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'book_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['book.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('book.settings');
    // @phpstan-ignore-next-line
    $types = node_type_get_names();

    $allowed_types = $config->get('allowed_types') ?? [];

    $allowed_types_indexed = [];
    foreach ($allowed_types as $item) {
      $allowed_types_indexed[$item['content_type']] = $item['child_type'];
    }

    $form['allowed_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed content types and their children'),
    ];

    foreach ($types as $type => $label) {
      $form['allowed_types'][$type] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'allowed-types-wrapper-' . $type],
      ];

      $form['allowed_types'][$type]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => isset($allowed_types_indexed[$type]),
        '#parents' => ['allowed_types', $type, 'enabled'],
        '#id' => 'allowed-types-' . $type . '-enabled',
      ];

      $form['allowed_types'][$type]['child_type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Allowed child type for %type', ['%type' => $label]),
        '#options' => $types,
        '#default_value' => $allowed_types_indexed[$type] ?? NULL,
        '#parents' => ['allowed_types', $type, 'child_type'],
        '#id' => 'allowed-types-' . $type . '-child-type',
        '#states' => [
          'visible' => [
            ':input#allowed-types-' . $type . '-enabled' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    $form['book_sort'] = [
      '#type' => 'radios',
      '#title' => $this->t('Book list sorting for administrative pages, outlines, and menus'),
      '#default_value' => $config->get('book_sort'),
      '#options' => [
        'weight' => $this->t('Sort by weight'),
        'title' => $this->t('Sort alphabetically by title'),
      ],
      '#required' => TRUE,
    ];
    $form['truncate_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Truncate the book label'),
      '#description' => $this->t('Truncate the book label if it is longer than @length characters.', [
        '@length' => Settings::get('book.truncate_limit', 30),
      ]),
      '#default_value' => $config->get('truncate_label'),
      '#config_target' => 'book.settings:truncate_label',
    ];

    $form['use_parent_selector'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use new parent selector javascript library'),
      '#description' => $this->t('Use new experimental javascript library to use nested parent selectors.'),
      '#default_value' => $config->get('use_parent_selector'),
      '#config_target' => 'book.settings:use_parent_selector',
    ];

    $form['use_alternative_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use alternative form for creating a book'),
      '#description' => $this->t('Use new experimental form that uses checkboxes for creating a new book.'),
      '#default_value' => $config->get('use_alternative_form'),
      '#config_target' => 'book.settings:use_alternative_form',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValue('allowed_types') ?? [];
    // Check if there is at least one child type for enabled content types.
    foreach ($values as $content_type => $type_data) {
      if (!empty($type_data['enabled'])) {
        if (empty($type_data['child_type'])) {
          $form_state->setErrorByName("allowed_types][$content_type][child_type", $this->t('You must select a child type for the enabled content type.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $values = $form_state->getValue('allowed_types') ?? [];
    $allowed_types_config = [];
    foreach ($values as $content_type => $type_data) {
      if (!empty($type_data['enabled'])) {
        $allowed_types_config[] = [
          'content_type' => $content_type,
          'child_type' => $type_data['child_type'] ?? "",
        ];
      }
    }

    $this->config('book.settings')
      ->set('allowed_types', $allowed_types_config)
      ->set('book_sort', $form_state->getValue('book_sort'))
      ->save();
  }

}
