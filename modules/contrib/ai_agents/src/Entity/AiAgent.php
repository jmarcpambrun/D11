<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai_agents\AiAgentInterface;

/**
 * Defines the AI Agent entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_agent",
 *   label = @Translation("AI Agent"),
 *   label_collection = @Translation("AI Agents"),
 *   label_singular = @Translation("AI Agent"),
 *   label_plural = @Translation("AI Agents"),
 *   label_count = @PluralTranslation(
 *     singular = "@count AI Agent",
 *     plural = "@count AI Agents",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\ai_agents\Form\AiAgentForm",
 *       "edit" = "Drupal\ai_agents\Form\AiAgentForm",
 *     },
 *   },
 *   config_prefix = "ai_agent",
 *   admin_permission = "administer ai_agent",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "default_information_tools",
 *     "system_prompt",
 *     "secured_system_prompt",
 *     "tools",
 *     "tool_usage_limits",
 *     "tool_settings",
 *     "orchestration_agent",
 *     "triage_agent",
 *     "max_loops",
 *     "max_loops_message",
 *     "masquerade_roles",
 *     "exclude_users_role",
 *     "structured_output_enabled",
 *     "structured_output_schema",
 *     "guardrail_set",
 *     "hostname_filter_disabled",
 *     "provider_config",
 *     "short_term_memory_plugin",
 *     "short_term_memory_config",
 *   },
 * )
 */
final class AiAgent extends ConfigEntityBase implements AiAgentInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    // Normalize NULL to empty arrays for sequence-typed properties to prevent
    // TypeErrors when loading config that was saved without these values set.
    foreach (['tools', 'tool_settings', 'tool_usage_limits', 'masquerade_roles', 'short_term_memory_config'] as $property) {
      if (!isset($values[$property]) || $values[$property] === NULL) {
        $values[$property] = [];
      }
    }
    parent::__construct($values, $entity_type);
  }

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description;

  /**
   * The dynamic context tools.
   */
  protected ?string $default_information_tools = NULL;

  /**
   * The system prompt (agent instructions).
   */
  protected string $system_prompt;

  /**
   * The secured system prompt that can contain secure instructions.
   */
  protected ?string $secured_system_prompt = NULL;

  /**
   * The tools that can be used.
   */
  protected array $tools = [];

  /**
   * The tool usage limits.
   */
  protected array $tool_usage_limits = [];

  /**
   * The tool settings.
   */
  protected array $tool_settings = [];

  /**
   * Is this an orchestration agent.
   */
  protected ?bool $orchestration_agent = NULL;

  /**
   * Is this a triage agent.
   */
  protected ?bool $triage_agent = NULL;

  /**
   * The max amount of loops.
   */
  protected int $max_loops = 3;

  /**
   * The message to display when max loops is reached.
   */
  protected ?string $max_loops_message = NULL;

  /**
   * The agent masquerade roles.
   */
  protected array $masquerade_roles = [];

  /**
   * Do not use users role.
   */
  protected bool $exclude_users_role = FALSE;

  /**
   * If the structured output is enabled.
   */
  protected ?bool $structured_output_enabled = NULL;

  /**
   * The structured output schema in JSON format.
   */
  protected ?string $structured_output_schema = NULL;

  /**
   * The guardrail set.
   */
  protected ?string $guardrail_set = NULL;

  /**
   * The possibility to turn off hostname whitelisting.
   */
  protected ?bool $hostname_filter_disabled = NULL;

  /**
   * The AI provider, model and configuration to run this agent with.
   *
   * Follows the ai.provider_config schema. When it is NULL, or when
   * use_default is TRUE, the agent falls back to the provider it inherits
   * from its caller, or to the site default for chat_with_tools.
   */
  protected ?array $provider_config = NULL;

  /**
   * The short term memory plugin id.
   */
  protected ?string $short_term_memory_plugin = NULL;

  /**
   * The configuration of the short term memory plugin.
   */
  protected array $short_term_memory_config = [];

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $tool_definitions = \Drupal::service('plugin.manager.ai.function_calls')->getDefinitions();
    foreach (array_keys($this->tools) as $tool_id) {
      if (!isset($tool_definitions[$tool_id])) {
        continue;
      }

      if (isset($tool_definitions[$tool_id]['provider'])) {
        $this->addDependency('module', $tool_definitions[$tool_id]['provider']);
      }
      foreach ($tool_definitions[$tool_id]['module_dependencies'] ?? [] as $module) {
        $this->addDependency('module', $module);
      }
    }

    // If the agent pins a specific AI provider, it depends on the module
    // supplying that provider plugin.
    if (!empty($this->provider_config['provider']) && empty($this->provider_config['use_default'])) {
      $provider_definitions = \Drupal::service('ai.provider')->getDefinitions();
      $provider_id = $this->provider_config['provider'];
      if (isset($provider_definitions[$provider_id]['provider'])) {
        $this->addDependency('module', $provider_definitions[$provider_id]['provider']);
      }
    }

    // Add the module providing the short term memory plugin as a dependency.
    if (!empty($this->short_term_memory_plugin)) {
      $memory_definitions = \Drupal::service('plugin.manager.ai.short_term_memory')->getDefinitions();
      if (isset($memory_definitions[$this->short_term_memory_plugin]['provider'])) {
        $this->addDependency('module', $memory_definitions[$this->short_term_memory_plugin]['provider']);
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);

    // Losing the module behind a pinned AI provider must not take the whole
    // agent with it. Reset the agent to the default provider instead, so all
    // of its other configuration survives.
    if (!empty($this->provider_config['provider']) && empty($this->provider_config['use_default'])) {
      $provider_definitions = \Drupal::service('ai.provider')->getDefinitions();
      $provider_id = $this->provider_config['provider'];
      $module = $provider_definitions[$provider_id]['provider'] ?? NULL;
      if ($module !== NULL && in_array($module, $dependencies['module'] ?? [], TRUE)) {
        $this->provider_config = [
          'use_default' => TRUE,
          'provider' => '',
          'model' => '',
          'config' => [],
        ];
        $changed = TRUE;
      }
    }

    // Clear the short term memory plugin and configuration when the module
    // providing that plugin is removed.
    if (!empty($this->short_term_memory_plugin)) {
      $memory_definitions = \Drupal::service('plugin.manager.ai.short_term_memory')->getDefinitions();
      $module = $memory_definitions[$this->short_term_memory_plugin]['provider'] ?? NULL;
      if ($module !== NULL && in_array($module, $dependencies['module'] ?? [], TRUE)) {
        $this->short_term_memory_plugin = '';
        $this->short_term_memory_config = [];
        $changed = TRUE;
      }
    }

    return $changed;
  }

}
