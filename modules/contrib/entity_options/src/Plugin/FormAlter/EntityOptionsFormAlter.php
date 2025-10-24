<?php

namespace Drupal\entity_options\Plugin\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\entity_options\Service\EntityOptionsPluginManager;
use Drupal\pluginformalter\Plugin\FormAlterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form alter class for Entity Options.
 *
 * @FormAlter(
 *    id = "entity_options_forms_alter",
 *    label = @Translation("Form alter hook for Entity Options."),
 *    base_form_id = {
 *      "node_type_form",
 *    },
 *  )
 *
 * @package Drupal\entity_options\Plugin\FormAlter
 * @phpstan-consistent-constructor
 */
class EntityOptionsFormAlter extends FormAlterBase {

  /**
   * Node option manager service.
   *
   * @var \Drupal\entity_options\Service\EntityOptionsPluginManager
   */
  protected $nodeOptionsManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityOptionsPluginManager $entity_options_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->nodeOptionsManager = $entity_options_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_options')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formAlter(array &$form, FormStateInterface &$form_state, $form_id) {
    /** @var \Drupal\node\Entity\NodeType $bundle */
    $bundle = $form_state->getFormObject()->getEntity();
    $options = $this->nodeOptionsManager->getTypeOptions($bundle);

    // Node type options container.
    $form['entity_options'] = [
      '#type' => 'details',
      '#group' => 'additional_settings',
      '#title' => $this->t('Entity Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#tree' => TRUE,
    ];
    $container = &$form['entity_options'];

    // Stop here if there are no options defined.
    if (empty($options)) {
      $container['empty'] = [
        '#prefix' => '<p>',
        '#suffix' => '<p>',
        '#markup' => $this->t('There are no options in this tab yet.'),
      ];
      return;
    }

    // For each option provide a form piece.
    /** @var \Drupal\entity_options\Plugin\EntityOptionInterface $option */
    foreach ($options as $name => $option) {
      // Option container.
      $container[$name] = [
        '#type' => 'details',
        '#title' => $option->getLabel(),
        '#description' => $option->getDescription(),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#tree' => TRUE,
      ];

      // Callbacks.
      $option_form = $option->settingsForm($form, $form_state, $option->configuration);

      // Check setting values, merge with defaults.
      if (!isset($type_options[$name])) {
        $type_options[$name] = [];
      }

      // Option status switch
      // Without overrides enabled, will as well define Flag option state.
      $container[$name]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable'),
        '#default_value' => $option->configuration['status'],
      ];

      // Flags do not have forms to collect parameters.
      if (empty($option_form)) {
        $container[$name]['enable']['#description'] = $this->t('This option is a flag option.');
      }
      // Parametric option require a form to collect parameters.
      else {
        foreach (Element::children($option_form) as $key) {
          $container[$name][$key] = $option_form[$key];
        }
      }

      // Display override settings when:
      // - overrides are allowed, and
      // - either option form is defined, or option is a Flag.
      if ($option->allowsOverrides()) {
        $container[$name]['overrides'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Allow per node overrides'),
          '#default_value' => $option->configuration['overrides'],
          '#description' => $this->t("When checked, will allow override this settings on per node basis."),
        ];
      }
    }

    array_unshift($form['actions']['submit']['#submit'], self::class . "::formAlterNodeTypeFormSubmit");
  }

  /**
   * Form submit callback.
   *
   * Updates node bundle third party settings.
   *
   * @param array $form
   *   Node type edit form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public static function formAlterNodeTypeFormSubmit($form, FormStateInterface $form_state): void {
    $bundle = $form_state->getFormObject()->getEntity();
    $submitted_options = $form_state->getValue('entity_options');
    foreach ($submitted_options as $key => $value) {
      $bundle->setThirdPartySetting('entity_options', $key, $value);
    }
  }

}
