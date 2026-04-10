<?php

namespace Drupal\burndown\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field widget "burndown_log_default".
 *
 * @FieldWidget(
 *   id = "burndown_log_default",
 *   label = @Translation("Burndown Log default"),
 *   field_types = {
 *     "burndown_log",
 *   }
 * )
 */
class BurndownLogDefaultWidget extends WidgetBase implements WidgetInterface {

  /*
   * @todo: Once issue is resolved in https://www.drupal.org/node/2053415
   * then implement dependency injection here.
   */
  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

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
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountInterface $current_user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition,
      $configuration['field_definition'], $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user')
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

    // Default widget is for comments. We need a custom form for time logging,
    // and the other types are automatically generated.
    $element['type'] = [
      '#type' => 'hidden',
      '#default_value' => 'comment',
    ];

    // Date created.
    $element['created'] = [
      '#type' => 'hidden',
      '#default_value' => time(),
    ];

    // Author.
    $element['uid'] = [
      '#type' => 'hidden',
      '#default_value' => $this->currentUser->id(),
    ];

    $element['comment'] = [
      '#title' => $this->t('Comment'),
      '#type' => 'text_format',
      '#format' => $item->comment['format'] ?? 'basic_html',
      '#default_value' => $item->comment['value'] ?? '',
    ];

    return $element;
  }

}
