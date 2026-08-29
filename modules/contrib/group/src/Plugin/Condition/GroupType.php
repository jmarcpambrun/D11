<?php

namespace Drupal\group\Plugin\Condition;

use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Group Type' condition.
 */
#[Condition(
  id: 'group_type',
  label: new TranslatableMarkup('Group type'),
  context_definitions: [
    'group' => new EntityContextDefinition(
      'entity:group',
      new TranslatableMarkup('Group'),
    ),
  ],
)]
class GroupType extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [];

    // Build a list of group type labels.
    $group_types = $this->entityTypeManager->getStorage('group_type')->loadMultiple();
    foreach ($group_types as $type) {
      $options[$type->id()] = $type->label();
    }

    // Show a series of checkboxes for group type selection.
    $form['group_types'] = [
      '#title' => $this->t('Group types'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $this->configuration['group_types'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['group_types'] = array_filter($form_state->getValue('group_types'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $group_types = $this->configuration['group_types'];

    // Format a pretty string if multiple group types were selected.
    if (count($group_types) > 1) {
      $last = array_pop($group_types);
      $group_types = implode(', ', $group_types);
      return $this->t('The group type is @group_types or @last', ['@group_types' => $group_types, '@last' => $last]);
    }

    // If just one was selected, return a simpler string.
    return $this->t('The group type is @group_type', ['@group_type' => reset($group_types)]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // If there are no group types selected and the condition is not negated, we
    // return TRUE because it means all group types are valid.
    if (empty($this->configuration['group_types']) && !$this->isNegated()) {
      return TRUE;
    }

    // Check if the group type of the group context was selected.
    $group = $this->getContextValue('group');
    return !empty($this->configuration['group_types'][$group->bundle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['group_types' => []] + parent::defaultConfiguration();
  }

}
