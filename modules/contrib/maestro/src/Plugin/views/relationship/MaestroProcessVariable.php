<?php

namespace Drupal\maestro\Plugin\views\relationship;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\maestro\Entity\MaestroTemplate;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;

/**
 * Relates a process to one of its process variables, chosen by name.
 *
 * A process has one maestro_process_variables row per variable, so the join
 * also matches on the variable's name: each process gets at most one row.
 * When a template is chosen too, only that template's processes are related.
 * Fields, filters and sorts on the variable's value can then use it.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("maestro_process_variable")
 */
class MaestroProcessVariable extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // The template the variable belongs to; '*' for any template.
    $options['template_id'] = ['default' => '*'];
    $options['variable_name'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $templates = MaestroTemplate::loadMultiple();
    uasort($templates, fn($a, $b) => strnatcasecmp($a->label(), $b->label()));
    $template_options = ['*' => $this->t('Any template')];
    foreach ($templates as $template) {
      $template_options[$template->id()] = $template->label();
    }
    // The template chosen so far: the submitted one while the form is being
    // rebuilt over AJAX, otherwise the saved one.
    $template_id = $form_state->getValue(['options', 'template_id']) ?? $this->options['template_id'];
    if (!isset($template_options[$template_id]) && !isset($templates[$template_id])) {
      // Keep a saved template selectable even if it has been deleted.
      $template_options[$template_id] = $template_id;
    }

    $form['template_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Template'),
      '#description' => $this->t("With a template chosen, only that template's processes are related. With \"Any template\", every process whose template has the variable is."),
      '#options' => $template_options,
      '#default_value' => $template_id,
      // Reload the variable list for the chosen template. The URL is this
      // relationship's own Views UI form: without it the request would go to
      // the form's original action, which after "Add and configure" is the
      // "Add relationships" form.
      '#ajax' => [
        'callback' => [static::class, 'variableNameAjax'],
        'wrapper' => 'maestro-process-variable-name',
        'event' => 'change',
        'url' => Url::fromRoute('views_ui.form_handler', [
          'js' => 'ajax',
          'view' => $this->view->storage->id(),
          'display_id' => $form_state->get('display_id') ?? $this->view->current_display,
          'type' => 'relationship',
          'id' => $this->options['id'],
        ]),
        'options' => ['query' => [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]],
      ],
      // Changing the template only reloads the variable list: only the
      // template is validated (and kept in the submitted values, which the
      // rebuilt form reads it from).
      '#limit_validation_errors' => [['options', 'template_id']],
    ];

    // The chosen template's variables, or for "Any template", every
    // variable name any template defines.
    $names = [];
    foreach ($template_id === '*' ? $templates : array_intersect_key($templates, [$template_id => TRUE]) as $template) {
      $names += array_combine(array_keys($template->variables ?? []), array_keys($template->variables ?? []));
    }
    $saved = $this->options['variable_name'];
    if ($saved !== '' && $template_id === $this->options['template_id'] && !isset($names[$saved])) {
      // Keep a saved variable selectable even if its template no longer has it.
      $names[$saved] = $saved;
    }
    uksort($names, 'strnatcasecmp');

    $form['variable_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Process variable'),
      '#description' => $this->t('Processes without this variable have no related variable, and are left out if this relationship is required.'),
      '#options' => $names,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => isset($names[$saved]) ? $saved : '',
      '#required' => TRUE,
      '#prefix' => '<div id="maestro-process-variable-name">',
      '#suffix' => '</div>',
    ];
  }

  /**
   * AJAX callback: the variable list for the chosen template.
   */
  public static function variableNameAjax(array $form, FormStateInterface $form_state) {
    return $form['options']['variable_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->options['variable_name'] === '') {
      return parent::adminSummary();
    }
    if ($this->options['template_id'] === '*') {
      return $this->t('Any template') . ': ' . $this->options['variable_name'];
    }
    $template = MaestroTemplate::load($this->options['template_id']);
    return ($template ? $template->label() : $this->options['template_id']) . ': ' . $this->options['variable_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Join only the chosen variable's row.
    $this->definition['extra'] = [
      ['field' => 'variable_name', 'value' => $this->options['variable_name']],
    ];
    // And, for a template's variable, only for that template's processes
    // (a condition on the process table, the left side of the join).
    if ($this->options['template_id'] !== '*') {
      $this->definition['extra'][] = ['left_field' => 'template_id', 'value' => $this->options['template_id']];
    }
    parent::query();
  }

}
