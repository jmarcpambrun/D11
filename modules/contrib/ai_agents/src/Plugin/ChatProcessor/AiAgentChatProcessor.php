<?php

namespace Drupal\ai_agents\Plugin\ChatProcessor;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Base\ChatProcessorBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs an AI Agent to generate the chat response.
 */
#[ChatProcessor(
  id: 'ai_agent',
  label: new TranslatableMarkup('AI Agent'),
  description: new TranslatableMarkup('Routes the chat input to a configured AI Agent and returns its response.'),
)]
class AiAgentChatProcessor extends ChatProcessorBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs the plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiAgentManager $agentManager,
    protected AiProviderPluginManager $providerManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.ai_agents'),
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'agent_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    foreach ($this->agentManager->getDefinitions() as $agent_id => $definition) {
      if (($definition['custom_type'] ?? NULL) !== 'config') {
        continue;
      }
      $options[$agent_id] = $definition['label'] ?? $agent_id;
    }
    natcasesort($options);

    $form['agent_id'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Agent'),
      '#description' => $this->t('Choose the AI Agent that will process the chat input.'),
      '#options' => $options,
      '#default_value' => $this->configuration['agent_id'] ?? '',
      '#empty_option' => $this->t('- Select an agent -'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['agent_id'] = $form_state->getValue('agent_id');
  }

  /**
   * {@inheritdoc}
   */
  public function doExecute(): ChatOutput {
    $agent_id = $this->configuration['agent_id'] ?? '';
    if ($agent_id === '' || !$this->agentManager->hasDefinition($agent_id)) {
      throw new \RuntimeException('No AI Agent configured for this ChatProcessor.');
    }

    $defaults = $this->providerManager->getDefaultProviderForOperationType('chat_with_complex_json');
    if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
      throw new \RuntimeException('No default Chat with Complex JSON provider/model is configured.');
    }

    /** @var \Drupal\ai_agents\PluginInterfaces\AiAgentInterface $agent */
    $agent = $this->agentManager->createInstance($agent_id);
    if ($agent->hasAccess() != AccessResult::allowed()) {
      throw new \RuntimeException(sprintf('Access denied for AI Agent "%s".', $agent_id));
    }

    $agent->setChatHistory($this->input->getMessages());
    $agent->setAiProvider($this->providerManager->createInstance($defaults['provider_id']));
    $agent->setModelName($defaults['model_id']);
    $agent->setAiConfiguration([]);
    $agent->setCreateDirectly(TRUE);

    $tags = ['chat_processor_ai_agent'];
    if ($this->threadId !== NULL) {
      $tags[] = 'ai_agent_thread_' . $this->threadId;
    }
    $agent->setUserInterface('ai_agent_chat_processor', $tags);

    $solvability = $agent->determineSolvability();
    $response = match ($solvability) {
      AiAgentInterface::JOB_NEEDS_ANSWERS => implode("\n", (array) $agent->askQuestion()),
      AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION => (string) $agent->answerQuestion(),
      AiAgentInterface::JOB_INFORMS => (string) $agent->inform(),
      AiAgentInterface::JOB_SOLVABLE => (string) $agent->solve(),
      default => 'Sorry, the agent could not solve this. Please rephrase the request.',
    };

    return new ChatOutput(
      new ChatMessage('assistant', $response),
      $response,
      [
        'agent_id' => $agent_id,
        'solvability' => $solvability,
      ],
    );
  }

}
