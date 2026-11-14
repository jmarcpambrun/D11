<?php

namespace Drupal\ai_integration_eca_agents\Plugin\Action;

use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\ai_agents\Task\Task;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Triggers an agent.
 *
 * @Action(
 *   id = "eca_ai_trigger_agent",
 *   label = @Translation("AI: Trigger Agent"),
 *   description = @Translation("Triggers an AI Agent.")
 * )
 */
class TriggerAgent extends ConfigurableActionBase {

  /**
   * The AI Agent plugin manager.
   *
   * @var \Drupal\ai_agents\PluginManager\AiAgentManager
   */
  protected AiAgentManager $aiAgentManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->aiAgentManager = $container->get('plugin.manager.ai_agents');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    $result = AccessResult::allowed();
    try {
      $instructions = trim($this->tokenService->replace($this->configuration['instructions']));
      if ($instructions === '') {
        $result = AccessResult::forbidden($this->t('The instructions for the agent are empty.'));
      }
    }
    catch (\InvalidArgumentException $e) {
      $result = AccessResult::forbidden($this->t('The agent %agent does not exist.', [
        '%agent' => $this->configuration['agent'],
      ]));
    }
    return $return_as_object ? $result : $result->allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    /** @var \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent */
    $agent = $this->aiAgentManager->createInstance($this->configuration['agent']);
    $instructions = trim($this->tokenService->replace($this->configuration['instructions']));
    $agent->setTask(new Task($instructions));
    $solvability = $agent->determineSolvability();
    if ($solvability == AiAgentInterface::JOB_SOLVABLE) {
      $agent->solve();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'agent' => '',
      'instructions' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $agents = [];
    foreach ($this->aiAgentManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $agents[$plugin_id] = $plugin_definition['label'];
    }

    $form['agent'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Agent'),
      '#description' => $this->t('The AI Agent to trigger.'),
      '#default_value' => $this->configuration['agent'],
      '#options' => $agents,
      '#weight' => -20,
      '#eca_token_select_option' => TRUE,
    ];
    $form['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
      '#description' => $this->t('The instructions, which should run.'),
      '#default_value' => $this->configuration['instructions'],
      '#weight' => -10,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['agent'] = $form_state->getValue('agent');
    $this->configuration['instructions'] = $form_state->getValue('instructions');
    parent::submitConfigurationForm($form, $form_state);
  }

}
