<?php

namespace Drupal\drd\Plugin\AdvancedQueue\JobType;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\drd\ActionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract class for AdvancedQueue JobType plugins.
 */
abstract class Action extends JobTypeBase implements ActionInterface, ContainerFactoryPluginInterface {

  /**
   * Action plugin.
   *
   * @var \Drupal\drd\Plugin\Action\BaseInterface|bool
   */
  protected mixed $action;

  /**
   * Job parameters.
   *
   * @var array
   */
  protected array $payload = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The DRD action manager.
   *
   * @var \Drupal\drd\ActionManagerInterface
   */
  protected ActionManagerInterface $actionManager;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ActionManagerInterface $action_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->actionManager = $action_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Action {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.drd_action')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process(Job $job): JobResult {
    $this->payload = $job->getPayload();
    $this->action = $this->actionManager->instance($this->payload['action']);
    if (!$this->action) {
      return new JobResult(Job::STATE_FAILURE, 'Action plugin not found.');
    }

    try {
      $this->action->setArguments(json_decode($this->payload['arguments'], TRUE, 512, JSON_THROW_ON_ERROR));
    }
    catch (\JsonException $e) {
      return new JobResult(Job::STATE_FAILURE, 'Action payload invalid: ' . $e->getMessage());
    }

    $result = $this->processAction();
    $this->payload['output'] = $this->action->getOutput();
    $job->setPayload($this->payload);
    return new JobResult($result ? Job::STATE_SUCCESS : Job::STATE_FAILURE);
  }

}
