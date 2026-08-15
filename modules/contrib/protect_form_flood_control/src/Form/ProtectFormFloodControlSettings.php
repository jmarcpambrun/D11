<?php

namespace Drupal\protect_form_flood_control\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The main settings form for protect_form_flood_control.
 *
 * @package Drupal\protect_form_flood_control\Form
 */
class ProtectFormFloodControlSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'protect_form_flood_control.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'protect_form_flood_control_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('protect_form_flood_control.settings');
    if (!$form_state->has('forms_count')) {
      $this->init($form_state, $config);
    }
    $forms_counts = $form_state->get('forms_count');
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];
    $form['general']['protect_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Protect all forms'),
      '#default_value' => $config->get('general.protect_all'),
      '#description' => $this->t('When enabled, all the form are protected. <b>Be careful</b> with this option, all forms, included all forms related to the site configuration, will be protected by flood control. This option is not recommended unless you are well aware of the implications. With this option enabled, it is highly recommended that you set the IP addresses of the administrators in the allow list, or to give the Bypass permission to theses users.'),
    ];
    $form['general']['window'] = [
      '#type' => 'number',
      '#title' => $this->t('Window'),
      '#min' => 1,
      '#default_value' => $config->get('general.window'),
      '#description' => $this->t('The window of the flood control.'),
    ];
    $form['general']['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Threshold'),
      '#min' => 1,
      '#default_value' => $config->get('general.threshold'),
      '#description' => $this->t('The threshold of the flood control.'),
    ];
    $default_protected_ids = $config->get('general.protected_ids') ? implode("\r\n", $config->get('general.protected_ids')) : '';
    $form['general']['protected_ids'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Protected Form IDs'),
      '#default_value' => $default_protected_ids,
      '#description' => $this->t('Specify the form IDs that should be protected With Flood Control. Each form ID should be on a separate line. Wildcard (*) characters can be used. Base form IDs are supported. Examples of form id : comment_*, contact_*, simplenews_subscriber_form, node_form, node_article_edit_form, etc.'),
      '#states' => [
        'invisible' => [
          ':input[name="general[protect_all]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $default_unprotected_ids = $config->get('general.unprotected_ids') ? implode("\r\n", $config->get('general.unprotected_ids')) : '';
    $form['general']['unprotected_ids'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Unprotected form IDs'),
      '#default_value' => $default_unprotected_ids,
      '#description' => $this->t('Specify the form IDs that should be <b>NOT</b> protected With Flood Control. Each form ID should be on a separate line. Wildcard (*) characters can be used. Base form IDs are supported.'),
      '#states' => [
        'visible' => [
          ':input[name="general[protect_all]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $default_allowlist = $config->get('general.allowlist') ? implode("\r\n", $config->get('general.allowlist')) : '';
    $form['general']['allowlist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed IP addresses'),
      '#default_value' => $default_allowlist,
      '#description' => $this->t('Specify IP addresses that should bypass Flood Control. Each IP address should be on a separate line. Wildcard (*) characters can be used (for example, 192.0.2.*).'),
    ];
    $form['general']['log'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log submissions blocked'),
      '#default_value' => $config->get('general.log'),
      '#description' => $this->t('When enabled, the form submissions blocked will be logged.'),
    ];

    $form['forms'] = [
      '#type' => 'details',
      '#title' => $this->t('Individual form configuration'),
      '#description' => $this->t('You can override general configuration per form ID.'),
      '#tree' => TRUE,
      '#open' => TRUE,
      '#prefix' => '<div id="forms">',
      '#suffix' => '</div>',
    ];

    for ($i = 1; $i < $forms_counts + 1; $i++) {

      $form['forms'][$i] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['inline-elements', 'form__item'],
        ],
      ];
      $form['forms'][$i]['item'] = [
        '#type' => 'item',
        '#markup' => $i,
      ];
      $default_form_ids = $config->get("forms.{$i}.ids") ? implode("\r\n", $config->get("forms.{$i}.ids")) : '';
      $form['forms'][$i]['ids'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Form IDs'),
        '#rows' => 1,
        '#description' => $this->t('Specify the form IDs that should be protected With Flood Control. Each form ID should be on a separate line. Wildcard (*) characters can be used. Base form IDs are supported.'),
        '#default_value' => $this->getElementPropertyValue(['forms', $i, 'ids'], $form_state, $default_form_ids),
      ];
      $default_window = $config->get("forms.{$i}.window");
      $form['forms'][$i]['window'] = [
        '#type' => 'number',
        '#title' => $this->t('Window'),
        '#size' => 12,
        '#min' => 1,
        '#default_value' => $this->getElementPropertyValue(['forms', $i, 'window'], $form_state, $default_window),
        '#attributes' => [
          'placeholder' => $this->t('Window'),
        ],
      ];
      $default_threshold = $config->get("forms.{$i}.threshold");
      $form['forms'][$i]['threshold'] = [
        '#type' => 'number',
        '#title' => $this->t('Threshold'),
        '#size' => 12,
        '#min' => 1,
        '#default_value' => $this->getElementPropertyValue(['forms', $i, 'window'], $form_state, $default_threshold),
        '#attributes' => [
          'placeholder' => $this->t('Threshold'),
        ],
      ];
      $form['forms'][$i]['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => 'op-delete-item-' . $i,
        '#submit' => ['::deleteItemCallback'],
        '#ajax' => [
          'callback' => '::ajaxDeleteItemCallback',
          'wrapper' => 'forms',
        ],
        '#attributes' => [
          'data-delete-item-id' => $i,
          'class' => ['button', 'button--trash'],
        ],
      ];
    }

    $form['forms']['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => [],
      ],
    ];

    $form['forms']['actions']['add_form_configuration'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add a form configuration'),
      '#name' => 'button-add-form-configuration',
      '#submit' => ['::addFormConfigurationCallback'],
      '#attributes' => [
        'class' => [],
      ],
      '#ajax' => [
        'event' => 'click',
        'method' => 'replace',
        'wrapper' => 'forms',
        'callback' => '::ajaxAddFormConfigurationCallback',
      ],
    ];

    $form['show_ids'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display form IDs and base form IDs'),
      '#default_value' => $config->get('show_ids'),
      '#description' => $this->t('When enabled, the form IDs and base form IDs of all forms on every page will be displayed to any user with permission to view the form id. This should only be turned on temporarily in order to easily determine the form IDs to use.'),
    ];
    $form['#attached']['library'][] = 'protect_form_flood_control/admin';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $values = $form_state->cleanValues()->getValues();
    $this->massageValues($values);
    $this->config('protect_form_flood_control.settings')
      ->set('general', $values['general'])
      ->set('forms', $values['forms'])
      ->set('show_ids', $values['show_ids'])
      ->save();
  }

  /**
   * Massage the values before saving them.
   *
   * @param array $values
   *   The values array to massage.
   */
  protected function massageValues(&$values) {
    unset($values['forms']['actions']);
    $protected_ids = explode("\r\n", $values['general']['protected_ids']);
    $values['general']['protected_ids'] = array_filter($protected_ids);
    $unprotected_ids = explode("\r\n", $values['general']['unprotected_ids']);
    $values['general']['unprotected_ids'] = array_filter($unprotected_ids);
    $allowlist = explode("\r\n", $values['general']['allowlist']);
    $values['general']['allowlist'] = array_filter($allowlist);
    foreach ($values['forms'] as $key => $form) {
      $values['forms'][$key]['ids'] = explode("\r\n", $form['ids']);
      unset($values['forms'][$key]['item']);
      unset($values['forms'][$key]['delete']);
    }
  }

  /**
   * Get element property value.
   *
   * @param mixed $property
   *   The requested property.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param mixed $default
   *   A default value.
   *
   * @return array|mixed|null
   *   The requested value, if any.
   */
  protected function getElementPropertyValue($property, FormStateInterface $form_state, $default = '') {
    $user_input = $form_state->getUserInput();
    if (is_array($property)) {
      $user_input_value = NestedArray::getValue($user_input, $property, $key_exists);
    }
    else {
      $user_input_value = !empty($user_input[$property]) ? $user_input[$property] : NULL;
    }
    if (!empty($user_input_value)) {
      return $user_input_value;
    }
    elseif ($form_state->hasValue($property)) {
      return $form_state->getValue($property);
    }

    else {
      return $default;
    }
  }

  /**
   * Initialize the form state before the first form build.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Config\Config $config
   *   The configuration.
   */
  protected function init(FormStateInterface $form_state, Config $config) {
    $forms = $config->get('forms') ?: [];
    $count = count($forms);
    $form_state->set('forms_count', $count);
  }

  /**
   * Delete item callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function deleteItemCallback(array $form, FormStateInterface $form_state) {
    $id = $form_state->getTriggeringElement()['#attributes']['data-delete-item-id'];
    $forms_count = $form_state->get('forms_count');
    $user_input = $form_state->getUserInput();
    unset($user_input['forms'][$id]);
    $forms = [];
    $i = 1;
    foreach ($user_input['forms'] as $values) {
      $values['value'] = $i;
      $forms[$i] = $values;
      $i++;
    }
    $user_input['forms'] = $forms;
    $form_state->setUserInput($user_input);
    $form_state->set('forms_count', $forms_count - 1);
    $form_state->setRebuild();
  }

  /**
   * Ajax delete item callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  public function ajaxDeleteItemCallback(array $form, FormStateInterface $form_state) {
    return $form['forms'];
  }

  /**
   * Add form configuration callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addFormConfigurationCallback(array $form, FormStateInterface $form_state) {
    $forms_count = $form_state->get('forms_count');
    $forms_count++;
    $form_state->set('forms_count', $forms_count);
    $form_state->setRebuild();
  }

  /**
   * Ajax add form configuration callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  public function ajaxAddFormConfigurationCallback(array $form, FormStateInterface $form_state) {
    return $form['forms'];
  }

}
