<?php

namespace Drupal\maestro_reactflow_template_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_reactflow_template_builder\Ajax\FireJavascriptCommand;

/**
 * The ReactFlow builder's template validity check, shown in a modal.
 */
class MaestroReactFlowValidityCheckForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'maestro_reactflow_validity_check';
  }

  /**
   * Ajax callback: closes the modal and updates the builder's validation notice.
   */
  public function closeForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $items = MaestroEngine::performTemplateValidityCheck($form_state->getValue('template_machine_name'));
    $command = count($items['failures']) > 0 ? 'signalValidationRequired' : 'turnOffValidationRequired';
    $response->addCommand(new FireJavascriptCommand($command, []));
    $response->addCommand(new HtmlCommand('#template-validity-check', $form));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $templateMachineName = '') {
    $template = MaestroEngine::getTemplate($templateMachineName);
    if ($template == NULL) {
      return [
        '#title' => $this->t('Error!'),
        '#markup' => $this->t("The template you are attempting to add a task to doesn't exist"),
      ];
    }

    $items = MaestroEngine::performTemplateValidityCheck($templateMachineName);

    $form = [
      '#title' => $this->t('Validity Check'),
    ];

    if (count($items['failures'])) {
      $form['#prefix'] = '<div id="template-validity-check" class="messages messages--error">'
        . $this->renderIssues($items['failures'], $this->t('Failure Note'))
        . '</div>';
    }
    else {
      $form['#prefix'] = '<div id="template-validity-check" class="messages messages--status">' . $this->t('Validity Check Passed') . '</div>';
    }

    if (count($items['information'])) {
      $form['#prefix'] .= '<div id="template-validity-check-information" class="messages messages--warning">'
        . $this->renderIssues($items['information'], $this->t('Information Note'))
        . '</div>';
    }

    $form['template_machine_name'] = [
      '#type' => 'hidden',
      '#default_value' => $templateMachineName,
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Save Template Validity'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'closeForm'],
        'wrapper' => '',
      ],
    ];
    return $form;
  }

  /**
   * Renders the validity check's failures or information items as HTML.
   */
  protected function renderIssues(array $items, $noteLabel) {
    $html = '';
    foreach ($items as $item) {
      $html .= '<div class="template-validity-check-issue">'
        . '<span class="template-validity-check-label">' . $this->t('Task ID') . ': </span>' . Html::escape((string) $item['taskID']) . '<br>'
        . '<span class="template-validity-check-label">' . $this->t('Task Label') . ': </span>' . Html::escape((string) $item['taskLabel']) . '<br>'
        . '<span class="template-validity-check-label">' . $noteLabel . ': </span>' . $item['reason'] . '<br>'
        . '</div>';
    }
    return $html;
  }

}
