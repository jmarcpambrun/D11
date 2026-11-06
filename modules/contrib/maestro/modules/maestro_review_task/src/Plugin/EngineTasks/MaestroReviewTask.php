<?php

namespace Drupal\maestro_review_task\Plugin\EngineTasks;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\MaestroTaskTrait;

/**
 * Maestro Review Task Plugin.
 *
 * A ready-made interactive task: the template builder configures everything,
 * so no handler function or custom form is needed. It shows instructions to
 * the person doing the task, links to the workflow's content (its entity
 * identifiers), and renameable Accept and Reject buttons.
 *
 * Accept completes the task with TASK_STATUS_SUCCESS; Reject completes it with
 * TASK_STATUS_CANCEL, so an IF task "By Last Task Status" can branch on it.
 *
 * @Plugin(
 *   id = "MaestroReviewTask",
 *   task_description = @Translation("An interactive task with instructions, links to the workflow's content and renameable Accept/Reject buttons."),
 * )
 */
class MaestroReviewTask extends PluginBase implements MaestroEngineTaskInterface {

  use MaestroTaskTrait;

  /**
   * Link modes for an entity identifier.
   */
  const MODE_VIEW = 'view';
  const MODE_VIEW_EDIT = 'view_edit';

  /**
   * Constructor.
   *
   * @param array $configuration
   *   [0] is the process ID and [1] the queue ID, when executing.
   */
  public function __construct($configuration = NULL) {
    if (is_array($configuration)) {
      $this->processID = $configuration[0];
      $this->queueID = $configuration[1];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isInteractive() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription() {
    return $this->t('Review Task');
  }

  /**
   * {@inheritDoc}
   */
  public function description() {
    return $this->t('Review Task: instructions, links to the workflow content and Accept/Reject buttons.');
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginId() {
    return 'MaestroReviewTask';
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    // The same blue as Maestro's other interactive tasks.
    return '#0000ff';
  }

  /**
   * {@inheritDoc}
   *
   * Like Maestro's own Interactive task: runs once and stays open until the
   * assigned person completes it with Accept or Reject.
   */
  public function execute() {
    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($this->queueID);
    \Drupal::moduleHandler()->invokeAll('maestro_pre_task_execute', [$this->queueID, $this->processID, MaestroEngine::getTaskIdFromQueueId($this->queueID)]);
    $queueRecord->set('run_once', 1);
    $queueRecord->save();
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getExecutableForm($modal, MaestroExecuteInteractive $parent) {
    $task = MaestroEngine::getTemplateTaskByQueueID($this->queueID);
    $review = $task['data']['review'] ?? [];

    $form['#attached']['library'][] = 'maestro_review_task/review_task';
    $form['#attributes']['class'][] = 'maestro-review-task';

    // Instructions, with Maestro tokens replaced for this process.
    $preamble = $review['preamble']['value'] ?? '';
    if (trim($preamble) !== '') {
      $preamble = \Drupal::token()->replace($preamble, [
        'maestro' => [
          'task' => $task,
          'queueID' => $this->queueID,
          'processID' => $this->processID,
        ],
      ]);
      $form['preamble'] = [
        '#type' => 'processed_text',
        '#text' => $preamble,
        '#format' => $review['preamble']['format'] ?? filter_default_format(),
        '#prefix' => '<div class="maestro-review-task__preamble">',
        '#suffix' => '</div>',
        '#weight' => -20,
      ];
    }

    $entities = $this->buildEntityLinks($review);
    if ($entities) {
      $form['entities'] = [
        '#theme' => 'item_list',
        '#items' => $entities,
        '#attributes' => ['class' => ['maestro-review-task__entities']],
        '#weight' => -10,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];
    $form['actions']['review_accept'] = [
      '#type' => 'submit',
      '#name' => 'review_accept',
      '#value' => $review['accept_label'] ?? $this->t('Accept'),
      '#button_type' => 'primary',
    ];
    if (!empty($review['show_reject'])) {
      $form['actions']['review_reject'] = [
        '#type' => 'submit',
        '#name' => 'review_reject',
        '#value' => $review['reject_label'] ?? $this->t('Reject'),
        '#attributes' => ['class' => ['button--danger']],
      ];
    }

    // In a modal, Maestro's completeForm() closes the dialog and redirects.
    if ($modal == 'modal') {
      foreach (['review_accept', 'review_reject'] as $button) {
        if (isset($form['actions'][$button])) {
          $form['actions'][$button]['#ajax'] = [
            'callback' => [$parent, 'completeForm'],
            'wrapper' => '',
          ];
        }
      }
    }
    // Where completeForm() sends the user once a modal task is done.
    $form['return_path'] = [
      '#type' => 'hidden',
      '#default_value' => $task['data']['redirect_to'] ?? 'taskconsole',
    ];

    return $form;
  }

  /**
   * Builds the links to the entity identifiers chosen for this task.
   *
   * View is offered only when the user may view the entity; Edit only when
   * the identifier allows editing and the user may edit it. Identifiers that
   * don't exist (yet) in this process are skipped.
   *
   * @return array
   *   Render arrays, one per entity.
   */
  protected function buildEntityLinks(array $review): array {
    $items = [];
    $link_attributes = !empty($review['new_tab']) ? ['target' => '_blank', 'rel' => 'noopener'] : [];
    $entity_type_manager = \Drupal::entityTypeManager();

    foreach ($review['identifiers'] ?? [] as $identifier) {
      if (empty($identifier['show']) || empty($identifier['unique_id'])) {
        continue;
      }
      $unique_id = $identifier['unique_id'];
      $fields = MaestroEngine::getEntityIdentiferFieldsByUniqueID($this->processID, $unique_id)[$unique_id] ?? [];
      $entity_type_id = $fields['entity_type'] ?? '';
      $entity_id = $fields['entity_id'] ?? '';
      if ($entity_type_id === '' || $entity_id === '' || !$entity_type_manager->hasDefinition($entity_type_id)) {
        continue;
      }
      $entity = $entity_type_manager->getStorage($entity_type_id)->load($entity_id);
      if (!$entity instanceof EntityInterface) {
        continue;
      }

      $links = [];
      if ($entity->hasLinkTemplate('canonical') && $entity->access('view')) {
        $links[] = Link::fromTextAndUrl($this->t('View'), $entity->toUrl('canonical', ['attributes' => $link_attributes]))->toRenderable();
      }
      if (($identifier['mode'] ?? self::MODE_VIEW) === self::MODE_VIEW_EDIT && $entity->hasLinkTemplate('edit-form') && $entity->access('update')) {
        $links[] = Link::fromTextAndUrl($this->t('Edit'), $entity->toUrl('edit-form', ['attributes' => $link_attributes]))->toRenderable();
      }

      $label = $entity->label();
      if ($label === NULL || $label === '') {
        $label = $entity->getEntityType()->getLabel() . ' ' . $entity->id();
      }
      $type_label = $entity->getEntityType()->getLabel();
      if ($entity instanceof ContentEntityInterface && $entity->getEntityType()->hasKey('bundle')) {
        $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
        $type_label = $bundle_info[$entity->bundle()]['label'] ?? $type_label;
      }

      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['maestro-review-task__entity']],
        // html_tag rather than #plain_text: the latter loses its text inside a form's item list.
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => Html::escape((string) $label),
          '#attributes' => ['class' => ['maestro-review-task__entity-label']],
        ],
        'type' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => Html::escape((string) $type_label),
          '#attributes' => ['class' => ['maestro-review-task__entity-type']],
        ],
        'links' => $links
          ? ['#type' => 'container', '#attributes' => ['class' => ['maestro-review-task__entity-links']]] + $links
          : ['#markup' => '<em>' . $this->t('You do not have access to this content.') . '</em>'],
      ];
    }
    return $items;
  }

  /**
   * {@inheritDoc}
   */
  public function handleExecuteSubmit(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement()['#name'] ?? '';
    if (!in_array($button, ['review_accept', 'review_reject'], TRUE)) {
      return;
    }
    $queueID = intval($form_state->getValue('maestro_queue_id')) ?: $this->queueID;
    // MaestroInteractiveFormBase only calls this for the assigned user; checked again to be safe.
    if (!MaestroEngine::canUserExecuteTask($queueID, \Drupal::currentUser()->id())) {
      return;
    }

    MaestroEngine::completeTask($queueID, \Drupal::currentUser()->id());
    if ($button === 'review_reject') {
      // Same as the Content Type task's Reject: complete, then flag as cancelled.
      MaestroEngine::setTaskStatus($queueID, TASK_STATUS_CANCEL);
    }

    // A modal is closed by completeForm(); a full page needs a redirect.
    $task = MaestroEngine::getTemplateTaskByQueueID($queueID);
    if (($task['data']['modal'] ?? '') !== 'modal') {
      $path = trim($task['data']['redirect_to'] ?? '', '/') ?: 'taskconsole';
      $form_state->setRedirectUrl(Url::fromUserInput('/' . $path));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskEditForm(array $task, $templateMachineName) {
    $review = $task['data']['review'] ?? [];

    $form['modal'] = [
      '#type' => 'select',
      '#title' => $this->t('Task presentation'),
      '#description' => $this->t('Show the task in a dialog over the task console, or on its own page.'),
      '#options' => [
        'notmodal' => $this->t('Full page'),
        'modal' => $this->t('Modal dialog'),
      ],
      '#default_value' => $task['data']['modal'] ?? 'notmodal',
    ];
    $form['redirect_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Return path'),
      '#description' => $this->t('Where the user goes after Accept or Reject, relative to the site. Defaults to taskconsole.'),
      '#default_value' => $task['data']['redirect_to'] ?? 'taskconsole',
    ];

    $form['review_preamble'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Instructions'),
      '#description' => $this->t('Shown at the top of the task to the person doing it. Maestro tokens are allowed, e.g. [maestro:process-variable-value:topic].'),
      '#default_value' => $review['preamble']['value'] ?? '',
      '#format' => $review['preamble']['format'] ?? filter_default_format(),
      '#rows' => 5,
    ];
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['review_token_tree'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['maestro'],
      ];
    }

    // The token browser can't list process variables (they differ per template), so list this
    // template's here with the token that shows each one's value.
    $variables = array_keys(MaestroEngine::getTemplateVariables($templateMachineName) ?: []);
    $form['review_pv_tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Show a process variable'),
      '#description' => $this->t("Paste a token into the instructions above to show that process variable's value at the moment the task is opened. Values are shown as plain text."),
      '#open' => FALSE,
    ];
    $form['review_pv_tokens']['list'] = [
      '#type' => 'table',
      '#header' => [$this->t('Process variable'), $this->t('Token to paste')],
      '#empty' => $this->t('This template has no process variables.'),
    ];
    foreach ($variables as $i => $name) {
      $form['review_pv_tokens']['list'][$i]['name'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => Html::escape($name),
      ];
      $form['review_pv_tokens']['list'][$i]['token'] = [
        '#type' => 'html_tag',
        '#tag' => 'code',
        '#value' => Html::escape('[maestro:process-variable-value:' . $name . ']'),
      ];
    }

    $form['review_accept_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accept button label'),
      '#default_value' => $review['accept_label'] ?? $this->t('Accept'),
      '#required' => TRUE,
      '#maxlength' => 64,
    ];
    $form['review_show_reject'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a Reject button'),
      '#description' => $this->t('Reject completes the task with the "cancelled" status, so an IF task set to "By Last Task Status" can branch on it.'),
      '#default_value' => $review['show_reject'] ?? TRUE,
    ];
    $form['review_reject_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reject button label'),
      '#default_value' => $review['reject_label'] ?? $this->t('Reject'),
      '#maxlength' => 64,
      '#states' => [
        'visible' => [':input[name="review_show_reject"]' => ['checked' => TRUE]],
      ],
    ];

    // Entity identifiers: those this template creates or uses, plus any already chosen.
    $saved = [];
    foreach ($review['identifiers'] ?? [] as $identifier) {
      $saved[$identifier['unique_id']] = $identifier;
    }
    $unique_ids = array_unique(array_merge(self::templateUniqueIds($templateMachineName), array_keys($saved)));

    $form['review_identifiers'] = [
      '#type' => 'table',
      '#caption' => $this->t('Content to link to'),
      '#header' => [$this->t('Show'), $this->t('Entity identifier'), $this->t('Links')],
      '#empty' => $this->t('This template does not create any entity identifiers yet. Add them below.'),
      '#tree' => TRUE,
    ];
    foreach (array_values($unique_ids) as $i => $unique_id) {
      $form['review_identifiers'][$i]['show'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show @id', ['@id' => $unique_id]),
        '#title_display' => 'invisible',
        '#default_value' => !empty($saved[$unique_id]['show']),
      ];
      $form['review_identifiers'][$i]['unique_id'] = [
        '#type' => 'hidden',
        '#value' => $unique_id,
        '#suffix' => '<code>' . htmlspecialchars($unique_id) . '</code>',
      ];
      $form['review_identifiers'][$i]['mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Links for @id', ['@id' => $unique_id]),
        '#title_display' => 'invisible',
        '#options' => [
          self::MODE_VIEW => $this->t('View only'),
          self::MODE_VIEW_EDIT => $this->t('View and edit'),
        ],
        '#default_value' => $saved[$unique_id]['mode'] ?? self::MODE_VIEW,
      ];
    }
    $form['review_identifiers_note'] = [
      '#markup' => '<p class="description">' . $this->t('Links only appear when the person doing the task has access: View when they may view the content, Edit when "View and edit" is chosen and they may edit it. Content that does not exist yet in the process is not listed.') . '</p>',
    ];
    $form['review_identifiers_extra'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Other entity identifiers'),
      '#description' => $this->t('Unique IDs not listed above, separated by commas. They are added as shown with "View only"; adjust them the next time you edit this task.'),
      '#default_value' => '',
    ];
    $form['review_new_tab'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open content links in a new browser tab'),
      '#default_value' => $review['new_tab'] ?? TRUE,
    ];

    return $form;
  }

  /**
   * Finds the entity identifier unique IDs a template creates or uses.
   *
   * @return string[]
   *   The unique IDs, in the order found.
   */
  public static function templateUniqueIds(?string $templateMachineName): array {
    $ids = [];
    $template = $templateMachineName ? MaestroEngine::getTemplate($templateMachineName) : NULL;
    foreach ($template->tasks ?? [] as $task) {
      $data = $task['data'] ?? [];
      $candidates = [
        // Content Type and Webform tasks.
        $data['unique_id'] ?? NULL,
        // Variable to Entity Identifier task.
        $data['vtei']['unique_id'] ?? NULL,
        // ECA event task and Set Process Variable plugins that read an identifier.
        $data['eca']['entity_identifier_manual'] ?? NULL,
        $data['spv']['entity_identifier'] ?? NULL,
      ];
      foreach ($candidates as $id) {
        if (is_string($id) && trim($id) !== '') {
          $ids[] = trim($id);
        }
      }
    }
    // A webform whose "Spawn Maestro Workflow" handler starts this template
    // registers its submission as "submission".
    if ($templateMachineName && \Drupal::moduleHandler()->moduleExists('webform')) {
      foreach (\Drupal::entityTypeManager()->getStorage('webform')->loadMultiple() as $webform) {
        foreach ($webform->getHandlers() as $handler) {
          if ($handler->getPluginId() === 'maestro' && ($handler->getSetting('maestro_template') ?? '') === $templateMachineName) {
            $ids[] = 'submission';
          }
        }
      }
    }
    return array_values(array_unique($ids));
  }

  /**
   * {@inheritDoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getValue('review_show_reject')) && trim((string) $form_state->getValue('review_reject_label')) === '') {
      $form_state->setErrorByName('review_reject_label', $this->t('Enter a label for the Reject button, or untick "Show a Reject button".'));
    }
    foreach (self::parseExtraIds((string) $form_state->getValue('review_identifiers_extra')) as $id) {
      if (!preg_match('/^[A-Za-z0-9_\-]+$/', $id)) {
        $form_state->setErrorByName('review_identifiers_extra', $this->t('"@id" is not a valid unique ID. Use letters, numbers, hyphens and underscores.', ['@id' => $id]));
      }
    }
    $redirect = trim((string) $form_state->getValue('redirect_to'));
    if ($redirect !== '' && preg_match('#^[a-z][a-z0-9+.\-]*://#i', $redirect)) {
      $form_state->setErrorByName('redirect_to', $this->t('The return path must be a path on this site, such as taskconsole.'));
    }
  }

  /**
   * Splits the "Other entity identifiers" field.
   */
  protected static function parseExtraIds(string $text): array {
    return array_values(array_filter(array_map('trim', explode(',', $text)), fn($id) => $id !== ''));
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {
    // Handler-less: Maestro builds the task from getExecutableForm().
    $task['handler'] = '';
    $task['data']['modal'] = $form_state->getValue('modal') === 'modal' ? 'modal' : 'notmodal';
    $task['data']['redirect_to'] = trim((string) $form_state->getValue('redirect_to'), " /") ?: 'taskconsole';

    $identifiers = [];
    foreach ($form_state->getValue('review_identifiers') ?: [] as $row) {
      if (!empty($row['unique_id'])) {
        $identifiers[$row['unique_id']] = [
          'unique_id' => $row['unique_id'],
          'show' => !empty($row['show']),
          'mode' => ($row['mode'] ?? '') === self::MODE_VIEW_EDIT ? self::MODE_VIEW_EDIT : self::MODE_VIEW,
        ];
      }
    }
    foreach (self::parseExtraIds((string) $form_state->getValue('review_identifiers_extra')) as $id) {
      $identifiers[$id] ??= ['unique_id' => $id, 'show' => TRUE, 'mode' => self::MODE_VIEW];
    }

    $preamble = $form_state->getValue('review_preamble') ?: [];
    $task['data']['review'] = [
      'preamble' => [
        'value' => $preamble['value'] ?? '',
        'format' => $preamble['format'] ?? filter_default_format(),
      ],
      'accept_label' => trim((string) $form_state->getValue('review_accept_label')) ?: 'Accept',
      'show_reject' => !empty($form_state->getValue('review_show_reject')),
      'reject_label' => trim((string) $form_state->getValue('review_reject_label')) ?: 'Reject',
      'new_tab' => !empty($form_state->getValue('review_new_tab')),
      'identifiers' => array_values($identifiers),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    if (empty($task['data']['review'])) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The Review Task has not been configured. Edit and save the task.'),
      ];
      return;
    }
    if (!empty($task['handler'])) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The Review Task has a handler set, so its own form will not be used. Edit and save the task to clear it.'),
      ];
    }
    $shown = array_filter($task['data']['review']['identifiers'] ?? [], fn($identifier) => !empty($identifier['show']));
    if (!$shown && trim($task['data']['review']['preamble']['value'] ?? '') === '') {
      $validation_information_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The Review Task shows no instructions and no content links; the person doing it will only see the buttons.'),
      ];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
