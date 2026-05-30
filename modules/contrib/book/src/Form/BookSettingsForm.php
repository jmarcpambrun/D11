<?php

namespace Drupal\book\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected TypedConfigManagerInterface $typedConfigManager,
    protected readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected readonly CacheBackendInterface $cache,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.bundle.info'),
      $container->get('cache.default'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('book.settings');
    $types = $this->entityTypeBundleInfo->getBundleLabels('node');

    $allowed_types = $config->get('allowed_types') ?? [];

    $allowed_types_indexed = [];
    foreach ($allowed_types as $item) {
      $allowed_types_indexed[$item['content_type']] = $item['child_type'];
    }

    $form['allowed_types'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allowed content types and their children'),
      '#tree' => TRUE,
      '#config_target' => 'book.settings:allowed_types',
    ];

    foreach ($types as $type => $label) {
      $form['allowed_types'][$type] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'allowed-types-wrapper-' . $type],
      ];

      $form['allowed_types'][$type]['content_type'] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#default_value' => isset($allowed_types_indexed[$type]),
        '#return_value' => $type,
        '#id' => 'allowed-types-' . $type . '-enabled',
      ];

      $form['allowed_types'][$type]['child_type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Allowed child type for %type', ['%type' => $label]),
        '#options' => $types,
        '#default_value' => $allowed_types_indexed[$type] ?? NULL,
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
      '#config_target' => 'book.settings:book_sort',
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
    // Check if there is at least one child type for enabled content types.
    foreach ($form_state->getValue('allowed_types') as $content_type => $type_data) {
      if (!empty($type_data['content_type']) && empty($type_data['child_type'])) {
        $form_state->setErrorByName("allowed_types][$content_type][child_type", $this->t('You must select a child type for the enabled content type.'));
      }
    }

    // Do not store disabled types, keeps config clean.
    $allowed_types = array_values(array_filter(
      $form_state->getValue('allowed_types'),
      static fn (array $type_data) => !empty($type_data['content_type']) && $type_data['child_type'] !== NULL
    ));
    $form_state->setValue('allowed_types', $allowed_types);

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('book.settings');
    $needs_cache_rebuild = $config->get('allowed_types') != $config->getOriginal('allowed_types');

    parent::submitForm($form, $form_state);

    if ($needs_cache_rebuild) {
      // Needed for \Drupal\book\Hook\BookHooks::entityBundleInfoAlter.
      $this->cache->deleteAll();
    }
  }

}
