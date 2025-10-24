<?php

namespace Drupal\entity_options\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\entity_options\Service\EntityOptionsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity Options field widget plugin.
 *
 * @FieldWidget(
 *    id = "entity_options",
 *    label = @Translation("Entity Options"),
 *    field_types = {
 *      "entity_options_map"
 *    },
 *    multiple_values = FALSE
 *  )
 * @phpstan-consistent-constructor
 */
class EntityOptionsWidget extends WidgetBase {

  /**
   * Node option manager service.
   *
   * @var \Drupal\entity_options\Service\EntityOptionsPluginManager
   */
  protected $nodeOptionsManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityOptionsPluginManager $entity_options_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->nodeOptionsManager = $entity_options_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.entity_options')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $form_state->getFormObject()->getEntity();
    if ($entity->getEntityType()->hasKey('bundle')) {
      $type = $entity->get($entity->getEntityType()->getKey('bundle'))->entity;
    }
    else {
      $type = $entity->getEntityType();
    }
    $options = array_filter(
      $this->nodeOptionsManager->getTypeOptions($type), static function ($option) {
        /** @var \Drupal\entity_options\Plugin\EntityOptionInterface $option */
        return $option->allowsOverrides() && $option->getOverride();
      });

    // Stop here if there are no options defined.
    if (empty($options)) {
      return $element;
    }

    $values = $items->get($delta)->getValue();

    $element += [
      '#type' => 'details',
      '#title' => $element['#title'],
      '#open' => FALSE,
      '#tree' => TRUE,
    ];
    // If the advanced settings tabs-set is available (normally rendered in the
    // second column on wide-resolutions), place the field as a details element
    // in this tab-set.
    if (isset($form['advanced'])) {
      $element['#group'] = 'advanced';
      $element['#weight'] = 30;
    }

    // For each option provide a form piece.
    /** @var \Drupal\entity_options\Plugin\EntityOptionInterface $option */
    foreach ($options as $name => $option) {
      // Option container.
      $element[$name] = [
        '#title' => $option->getLabel(),
        '#description' => $option->getDescription(),
      ];

      // Retrieve option form.
      // Flags do not have forms to collect parameters.
      if ($option->isFlag()) {
        $element[$name] += [
          '#type' => 'checkbox',
          '#default_value' => $values[$name],
        ];
      }
      else {
        $element[$name] += [
          '#type' => 'details',
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
          '#tree' => TRUE,
        ];
        $option_form = $option->settingsForm($form, $form_state, $option->configuration, $values[$name]);
        // Prevent messing with fieldset properties.
        foreach (Element::children($option_form) as $key) {
          $element[$name][$key] = $option_form[$key];
        }
      }
    }

    return $element;
  }

}
