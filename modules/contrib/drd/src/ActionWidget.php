<?php

namespace Drupal\drd;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\drd\Entity\BaseInterface;
use Drupal\drd\Plugin\Action\BaseConfigurableInterface;
use Drupal\drd\Plugin\Action\BaseEntityInterface;
use Drupal\drd\Plugin\Action\BaseInterface as ActionBaseInterface;
use Drupal\system\ActionConfigEntityInterface;

/**
 * The action widget service.
 *
 * @package Drupal\drd
 */
class ActionWidget implements ActionWidgetInterface {

  use MessengerTrait;

  /**
   * Mode for action manager.
   *
   * @var string
   */
  protected string $mode;

  /**
   * Number of actions found.
   *
   * @var int
   */
  protected int $count = 0;

  /**
   * Selected action entity.
   *
   * @var \Drupal\system\ActionConfigEntityInterface
   */
  protected ActionConfigEntityInterface $action;

  /**
   * An array of actions that can be executed.
   *
   * @var \Drupal\system\ActionConfigEntityInterface[]
   */
  protected array $actions = [];

  /**
   * The action storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $actionStorage;

  /**
   * List of entities for which actions should be executed.
   *
   * @var \Drupal\drd\Entity\BaseInterface[]
   */
  protected array $entities;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The queue manager.
   *
   * @var \Drupal\drd\QueueManager
   */
  protected QueueManager $queueManager;

  /**
   * ActionWidget constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\drd\QueueManager $queueManager
   *   The queue manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, QueueManager $queueManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->queueManager = $queueManager;
    $this->actionStorage = $this->entityTypeManager->getStorage('action');
    $this->setMode('drd');
  }

  /**
   * {@inheritdoc}
   */
  public function setMode(string $mode): ActionWidgetInterface {
    $this->mode = $mode;
    /** @var \Drupal\system\ActionConfigEntityInterface[] $actions */
    $actions = $this->actionStorage->loadMultiple();
    $this->actions = array_filter($actions, static function (ActionConfigEntityInterface $action) use ($mode) {
      return $action->getType() === $mode && $action->getPlugin()->access(NULL);
    });
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getActionPlugins(): array {
    $result = [];
    foreach ($this->actions as $action) {
      $plugin = $action->getPlugin();
      if ($plugin instanceof ActionBaseInterface) {
        $result[] = $plugin;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectedAction(): ActionConfigEntityInterface {
    return $this->action;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutedCount(): int {
    return $this->count;
  }

  /**
   * Get list of matching actions as a form API select list.
   *
   * @param array $options
   *   The list of options.
   *
   * @return array
   *   List of actions for select form element.
   */
  protected function getBulkOptions(array $options): array {
    $bulkOptions = [];
    // Filter the action list.
    foreach ($this->actions as $id => $action) {
      if (!empty($options['selected_actions'])) {
        $in_selected = in_array($id, $options['selected_actions'], TRUE);
        if (($options['include_exclude'] === 'include') && !$in_selected) {
          continue;
        }

        if (($options['include_exclude'] === 'exclude') && $in_selected) {
          continue;
        }
      }

      $bulkOptions[$id] = $action->label();
    }

    asort($bulkOptions);
    return $bulkOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state, array $options = []): void {
    $form['#type'] = 'container';
    $form['action'] = [
      '#type' => 'select',
      '#title' => t('Action'),
      '#options' => ['' => '-- ' . t('select')->render() . ' --'] + $this->getBulkOptions($options),
    ];
    $form['actions'] = [
      '#type' => 'container',
      '#weight' => 9,
      'submit' => [
        '#type' => 'submit',
        '#value' => t('Apply'),
      ],
    ];

    foreach ($form['action']['#options'] as $action_key => $action_label) {
      if (empty($action_key)) {
        continue;
      }
      $action = $this->actions[$action_key]->getPlugin();
      if ($action instanceof BaseConfigurableInterface) {
        $form[$action_key] = [
          '#type' => 'container',
          '#weight' => 8,
          '#states' => [
            'visible' => [
              '#edit-action' => ['value' => $action_key],
            ],
          ],
        ] + $action->buildConfigurationForm([], $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $action = $this->actions[$form_state->getValue('action')]->getPlugin();
    if ($action instanceof BaseConfigurableInterface) {
      $action->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setSelectedEntities(BaseInterface|array $entities): ActionWidgetInterface {
    $this->entities = is_array($entities) ? $entities : [$entities];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->action = $this->actions[$form_state->getValue('action')];
    /** @var \Drupal\drd\Plugin\Action\BaseInterface $actionPlugin */
    $actionPlugin = $this->action->getPlugin();
    if ($actionPlugin instanceof BaseConfigurableInterface) {
      $actionPlugin->submitConfigurationForm($form, $form_state);
    }

    if ($actionPlugin instanceof BaseEntityInterface) {
      $permittedEntities = [];
      foreach ($this->entities as $entity) {
        // Skip execution if the user did not have access.
        if (!$actionPlugin->access($entity)) {
          $this->messenger()->addMessage(t('No access to execute @action on the @entity_type_label @entity_label.', [
            '@action' => $this->action->label(),
            '@entity_type_label' => $entity->getEntityType()->getLabel(),
            '@entity_label' => $entity->label(),
          ]), 'error');
          continue;
        }

        $this->count++;
        $permittedEntities[] = $entity;
      }
      $this->queueManager->createItems($actionPlugin, $permittedEntities);
    }
    else {
      $this->count = 1;
      $this->queueManager->createItem($actionPlugin);
    }

  }

}
