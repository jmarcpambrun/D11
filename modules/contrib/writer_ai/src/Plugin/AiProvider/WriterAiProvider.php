<?php

namespace Drupal\writer_ai\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\writer_ai\WriterAiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of Writer.com AI provider.
 */
#[AiProvider(
  id: 'writerai',
  label: new TranslatableMarkup('WriterAI')
)]
class WriterAiProvider extends AiProviderClientBase implements ChatInterface {

  /**
   * The Writer AI client instance.
   *
   * @var \Drupal\writer_ai\WriterAiClient|null
   */
  protected ?WriterAiClient $client = NULL;

  /**
   * The Writer AI client service.
   *
   * @var \Drupal\writer_ai\WriterAiClient
   */
  protected WriterAiClient $writerAiClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('cache.default'),
      $container->get('key.repository'),
      $container->get('module_handler'),
      $container->get('event_dispatcher'),
      $container->get('file_system')
    );
    $instance->writerAiClient = $container->get('writer_ai.client');
    return $instance;
  }

  /**
   * Retrieves the list of configured models supported by this provider.
   *
   * @param string|null $operation_type
   *   The operation type, e.g., "chat".
   * @param array $capabilities
   *   Specific capabilities to filter models by.
   *
   * @return array
   *   An array of supported model configurations.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   *   Thrown if the models cannot be fetched.
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    try {
      $this->setAuthentication();
      $models = $this->client->loadModels();
      $result = [];
      if (isset($models['models']) && is_array($models['models'])) {
        foreach ($models['models'] as $model) {
          if (isset($model['id']) && isset($model['name'])) {
            $result[$model['id']] = $model['name'];
          }
        }
      }
      return $result;
    }
    catch (\Exception $e) {
      throw new AiResponseErrorException('Could not fetch models from API: ' . $e->getMessage());
    }
  }

  /**
   * Checks if the provider is usable for a given operation type.
   *
   * @param string|null $operation_type
   *   The type of operation, e.g., "chat".
   * @param array $capabilities
   *   Additional capabilities to check against.
   *
   * @return bool
   *   TRUE if the provider can be used; FALSE otherwise.
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (!$this->getConfig()->get('api_key')) {
      return FALSE;
    }

    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes());
    }

    return TRUE;
  }

  /**
   * Returns the operation types supported by this provider.
   *
   * @return array
   *   An array of supported operation types, e.g., ['chat'].
   */
  public function getSupportedOperationTypes(): array {
    return ['chat', 'chat_with_image_vision'];
  }

  /**
   * Retrieves the configuration for this plugin.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration object.
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('writer_ai.settings');
  }

  /**
   * Retrieves the API definition for this provider.
   *
   * The definition is loaded from a YAML file included with the module.
   *
   * @return array
   *   An array of API defaults defined in the YAML file.
   */
  public function getApiDefinition(): array {
    $definition = Yaml::parseFile(
      $this->moduleHandler->getModule('writer_ai')
        ->getPath() . '/definitions/api_defaults.yml'
    );
    return $definition;
  }

  /**
   * Configures settings for a specific model.
   *
   * @param string $model_id
   *   The ID of the model being configured.
   * @param array $generalConfig
   *   General configuration options.
   *
   * @return array
   *   The final model settings.
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupData(): array {
    return [
      'key_config_name' => 'api_key',
      'default_models' => [
        'chat' => 'palmyra-x5',
        'chat_with_image_vision' => 'palmyra-x5',
        'chat_with_complex_json' => 'palmyra-x5',
        'chat_with_structured_response' => 'palmyra-x5',
        'chat_with_tools' => 'palmyra-x5',
      ],
    ];
  }

  /**
   * Sets the authentication method for the provider.
   *
   * @param mixed $authentication
   *   The API key or other credentials. If null, loads from configuration.
   */
  public function setAuthentication(mixed $authentication = NULL): void {
    if (!$this->client) {
      $this->client = $this->writerAiClient;
    }
    $api_key = $authentication ?: $this->loadApiKey();
    if ($api_key) {
      $this->client->setApiKey($api_key);
    }
  }

  /**
   * Executes a chat operation with the AI model.
   *
   * @param array|string|ChatInput $input
   *   The input messages or configuration for the chat.
   * @param string $model_id
   *   The ID of the model to use.
   * @param array $tags
   *   Optional tags for additional metadata.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The response from the AI model.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   *   Thrown if unsupported roles are found in the input.
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->setAuthentication();

    if ($input instanceof ChatInput) {
      foreach ($input->getMessages() as $message) {
        if (!in_array($message->getRole(), ['model', 'user', 'system', 'assistant'])) {
          $error_message = sprintf('The role %s is not supported by Writer AI.', $message->getRole());
          throw new AiResponseErrorException($error_message);
        }
      }
    }

    try {
      $response = $this->client->model($model_id)->generate($input);
      $message = new ChatMessage('assistant', $response);
      return new ChatOutput($message, $response, []);
    }
    catch (\Exception $e) {
      throw new AiResponseErrorException('Error invoking model response: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(string $api_key = '') {
    $this->setAuthentication($api_key ?: NULL);
    return $this->client;
  }

  /**
   * Load API key from key module.
   */
  protected function loadApiKey(): string {
    return $this->keyRepository->getKey($this->getConfig()->get('api_key'))
      ->getKeyValue();
  }

}
