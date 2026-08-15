<?php

namespace Drupal\burndown\Plugin\Field\FieldWidget;

use Drupal\burndown\Entity\Task;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field widget "burndown_task_relationship_default".
 *
 * @FieldWidget(
 *   id = "burndown_task_relationship_default",
 *   label = @Translation("Burndown Task Relationship default"),
 *   field_types = {
 *     "burndown_task_relationship",
 *   }
 * )
 */
class BurndownTaskRelationshipDefaultWidget extends WidgetBase implements WidgetInterface {

  /*
   * @todo: Once issue is resolved in https://www.drupal.org/node/2053415
   * then implement dependency injection here.
   */

  /**
   * The burndown configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ConfigFactoryInterface $config_factory) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->config = $config_factory->get('burndown.config_settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition,
      $configuration['field_definition'], $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // $item is where the current saved values are stored.
    $item =& $items[$delta];

    // $element is already populated with #title, #description, #delta,
    // #required, #field_parents, etc.
    $element += [
      '#type' => 'fieldset',
    ];

    // Load the task.
    $task = NULL;
    if (isset($item->task_id)) {
      $task = Task::load($item->task_id);
    }

    // The other task.
    $element['task_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Task'),
      '#target_type' => 'burndown_task',
      '#selection_handler' => 'views',
      '#selection_settings' => [
        'view' => [
          'view_name' => 'burndown_task_by_ticket_id',
          'display_name' => 'entity_reference_1',
          'arguments' => [],
        ],
        'match_operator' => 'CONTAINS',
      ],
      '#default_value' => isset($item->task_id) ? $task : NULL,
    ];

    // Relationship type.
    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Relationship Type'),
      '#options' => $this->burndownRelationshipTypes(),
      '#default_value' => $item->type ?? '',
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * List of options for relationship type.
   */
  public function burndownRelationshipTypes() {
    $options = [];

    // Get config object.
    // $config = \Drupal::config('burndown.config_settings');.
    $list = $this->config->get('relationship_types');

    if (!empty($list)) {
      // List is a text string with one item per line.
      $list = preg_split("/\r\n|\n|\r/", $list);

      foreach ($list as $row) {
        // Rows are in the form "id|label".
        $val = explode('|', $row);
        $options[$val[0]] = strval($val[1]);
      }
    }

    return $options;
  }

}
