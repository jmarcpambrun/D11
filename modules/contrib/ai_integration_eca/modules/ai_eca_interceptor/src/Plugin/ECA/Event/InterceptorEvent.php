<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\ECA\Event;

use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\ai\Event\AiProviderResponseBaseEvent;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai_eca_interceptor\Service\EventTokenBuilder;
use Drupal\eca\Attribute\EcaEvent;
use Drupal\eca\Attribute\Token;
use Drupal\eca\Entity\Objects\EcaEvent as EcaEventObject;
use Drupal\eca\Plugin\ECA\Event\EventBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Plugin implementation of the AI ECA Interceptor events.
 */
#[EcaEvent(
  id: 'ai_eca_interceptor',
  deriver: 'Drupal\ai_eca_interceptor\Plugin\ECA\Event\InterceptorEventDeriver',
  version_introduced: '2.0.0',
)]
class InterceptorEvent extends EventBase {

  public const DERIVATIVE_PRE = 'ai_request';
  public const DERIVATIVE_POST = 'ai_response';
  public const DERIVATIVE_STREAM = 'ai_stream_finished';
  public const DERIVATIVE_FAILED = 'ai_response_failed';

  /**
   * Token builder.
   */
  protected EventTokenBuilder $tokenBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenBuilder = $container->get('ai_eca_interceptor.token_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * Every derivative declares its 'subscriber_priority' explicitly, even though
   * EventDeriverBase would default it to 0. The value is load-bearing rather
   * than incidental: it is the priority at which ECA's DynamicSubscriber
   * registers, and this module positions two of its own subscribers relative to
   * it.
   *
   * For the pre-request derivative it must stay greater than -10 so
   * GuardrailApplierSubscriber runs after ECA and can apply a guardrail set an
   * ECA action attached, and greater than -100 so ProviderRerouteSubscriber
   * runs last and sees the reroute metadata every ECA action wrote. The tie
   * with the ai module's own GuardrailsEventSubscriber, which also uses 0, is
   * resolved by module registration order — GuardrailApplierSubscriber exists
   * precisely because core wins that tie.
   *
   * Do not change these to non-zero values: EcaStorage requires a single
   * distinct priority per event name, and \Drupal\eca\PluginManager\Event
   * rejects two ECA event plugins claiming the same system event.
   *
   * @see \Drupal\ai_eca_interceptor\EventSubscriber\GuardrailApplierSubscriber
   * @see \Drupal\ai_eca_interceptor\EventSubscriber\ProviderRerouteSubscriber
   */
  public static function definitions(): array {
    return [
      self::DERIVATIVE_PRE => [
        'label' => 'AI request: before provider call',
        'event_name' => PreGenerateResponseEvent::EVENT_NAME,
        'event_class' => PreGenerateResponseEvent::class,
        'subscriber_priority' => 0,
      ],
      self::DERIVATIVE_POST => [
        'label' => 'AI response: after provider call',
        'event_name' => PostGenerateResponseEvent::EVENT_NAME,
        'event_class' => PostGenerateResponseEvent::class,
        'subscriber_priority' => 0,
      ],
      self::DERIVATIVE_STREAM => [
        'label' => 'AI response: stream finished',
        'event_name' => PostStreamingResponseEvent::EVENT_NAME,
        'event_class' => PostStreamingResponseEvent::class,
        'subscriber_priority' => 0,
      ],
      self::DERIVATIVE_FAILED => [
        'label' => 'AI response: failed',
        // AI core dispatches AiExceptionEvent without an explicit name, so the
        // Symfony event name is the fully qualified class name. This event
        // fires for every ai.provider consumer and supports failover via
        // setForcedOutputObject() and message rewriting via setMessage().
        //
        // @todo Switch 'event_name' to a static event name constant once the
        //   AI module provides one for AiExceptionEvent, instead of relying on
        //   the FQCN as the Symfony event name.
        'event_name' => AiExceptionEvent::class,
        'event_class' => AiExceptionEvent::class,
        'subscriber_priority' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'operation_type' => '',
      'provider_id' => '',
      'model_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['operation_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Operation type filter'),
      '#default_value' => $this->configuration['operation_type'],
      '#description' => $this->t('Limit to a specific AI operation type (e.g. "chat", "embeddings"). Leave empty for any.'),
    ];
    $form['provider_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Provider id filter'),
      '#default_value' => $this->configuration['provider_id'],
      '#description' => $this->t('Limit to a specific provider id. Leave empty for any.'),
    ];
    $form['model_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model id filter'),
      '#default_value' => $this->configuration['model_id'],
      '#description' => $this->t('Limit to a specific model id. Leave empty for any.'),
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['operation_type'] = (string) $form_state->getValue('operation_type');
    $this->configuration['provider_id'] = (string) $form_state->getValue('provider_id');
    $this->configuration['model_id'] = (string) $form_state->getValue('model_id');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function generateWildcard(string $eca_config_id, EcaEventObject $ecaEvent): string {
    $c = $ecaEvent->getConfiguration();
    $op = trim($c['operation_type'] ?? '') ?: '*';
    $pr = trim($c['provider_id'] ?? '') ?: '*';
    $md = trim($c['model_id'] ?? '') ?: '*';
    return $op . ':' . $pr . ':' . $md;
  }

  /**
   * {@inheritdoc}
   */
  public static function appliesForWildcard(Event $event, string $event_name, string $wildcard): bool {
    // AiExceptionEvent extends AiProviderRequestBaseEvent, so failed events are
    // covered by this single branch alongside the request/response events.
    if ($event instanceof AiProviderRequestBaseEvent) {
      $op = $event->getOperationType();
      $pr = $event->getProviderId();
      $md = $event->getModelId();
    }
    else {
      return FALSE;
    }
    [$fop, $fpr, $fmd] = array_pad(explode(':', $wildcard, 3), 3, '*');
    if ($fop !== '*' && $op !== $fop) {
      return FALSE;
    }
    if ($fpr !== '*' && $pr !== $fpr) {
      return FALSE;
    }
    if ($fmd !== '*' && $md !== $fmd) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  #[Token(
    name: 'event',
    description: 'The AI event.',
    properties: [
      new Token(name: 'machine_name', description: 'Machine name of the triggered ECA event (e.g. ai.pre_generate_response).'),
      new Token(name: 'request_thread_id', description: 'Unique id of the AI request thread.'),
      new Token(name: 'request_parent_id', description: 'Parent request id if this request was initiated from another AI request.'),
      new Token(name: 'provider_id', description: 'The AI provider id that will execute (or did execute) the call.'),
      new Token(name: 'model_id', description: 'The AI model id.'),
      new Token(name: 'operation_type', description: 'Operation type: chat, embeddings, moderation, speech_to_text, text_to_speech, etc.'),
      new Token(name: 'tags', description: 'Tags attached to the request (array).'),
      new Token(name: 'tag_count', description: 'Number of tags attached to the request.'),
      new Token(name: 'configuration', description: 'Provider configuration array (temperature, max_tokens, …).'),
      new Token(name: 'debug_data', description: 'Debug data attached to the request.'),
      new Token(name: 'metadata', description: 'All metadata on the event, including reroute directives.'),
      new Token(name: 'is_chat', description: '1 when the operation type is "chat", 0 otherwise.'),
      new Token(name: 'input', description: 'The raw input DTO flattened to an array (input->toArray()).'),
      new Token(name: 'input_class', description: 'Fully qualified class name of the input DTO.'),
      new Token(name: 'input_text', description: 'Best-effort textual representation of the input (chat prompts joined, summarization/translation/moderation text, etc.).'),
      new Token(name: 'chat_tools', description: 'Chat only. The declared tools rendered to an array.'),
      new Token(name: 'chat_tool_names', description: 'Chat only. The names of the declared tool functions (array).'),
      new Token(name: 'chat_tool_count', description: 'Chat only. Number of declared tool functions.'),
      new Token(name: 'chat_has_tools', description: 'Chat only. 1 when the request declares any tools, 0 otherwise.'),
      new Token(name: 'chat_has_schema', description: 'Chat only. 1 when the request declares a structured JSON schema, 0 otherwise.'),
      new Token(name: 'chat_streamed', description: 'Chat only. 1 when the request asked for streamed output, 0 otherwise.'),
      new Token(name: 'output', description: 'Response only. The normalized output converted to an array.', classes: [AiProviderResponseBaseEvent::class]),
      new Token(name: 'output_class', description: 'Response only. Fully qualified class name of the output DTO.', classes: [AiProviderResponseBaseEvent::class]),
      new Token(name: 'output_normalized', description: 'Response only. The normalized output (array form where possible).', classes: [AiProviderResponseBaseEvent::class]),
      new Token(name: 'output_raw', description: 'Response only. The provider\'s raw output payload.', classes: [AiProviderResponseBaseEvent::class]),
      new Token(name: 'output_metadata', description: 'Response only. Metadata returned by the provider.', classes: [AiProviderResponseBaseEvent::class]),
      new Token(name: 'output_text', description: 'Response only. Best-effort textual representation of the output.', classes: [AiProviderResponseBaseEvent::class]),
      new Token(name: 'output_is_streamed', description: 'Response only, chat. 1 when the output is a streamed iterator, 0 otherwise.', classes: [AiProviderResponseBaseEvent::class]),
      new Token(
        name: 'token_usage',
        description: 'Response only, chat. Token usage DTO broken out to fields.',
        classes: [AiProviderResponseBaseEvent::class],
        properties: [
          new Token(name: 'input', description: 'Input tokens consumed.'),
          new Token(name: 'output', description: 'Output tokens produced.'),
          new Token(name: 'total', description: 'Total tokens used.'),
          new Token(name: 'reasoning', description: 'Reasoning tokens used (if reported).'),
          new Token(name: 'cached', description: 'Cached tokens reused (if reported).'),
        ],
      ),
      new Token(
        name: 'rate_limits',
        description: 'Response only, chat. Provider rate limit DTO fields.',
        classes: [AiProviderResponseBaseEvent::class],
        properties: [
          new Token(name: 'max_requests', description: 'Rate limit max requests window.'),
          new Token(name: 'max_tokens', description: 'Rate limit max tokens window.'),
          new Token(name: 'remaining_requests', description: 'Remaining requests in the current window.'),
          new Token(name: 'remaining_tokens', description: 'Remaining tokens in the current window.'),
          new Token(name: 'reset_requests', description: 'Seconds until the request window resets.'),
          new Token(name: 'reset_tokens', description: 'Seconds until the token window resets.'),
        ],
      ),
      new Token(name: 'moderation_flagged', description: 'Response only, moderation. 1 when the response is flagged, 0 otherwise.', classes: [AiProviderResponseBaseEvent::class]),
      new Token(name: 'moderation_message', description: 'Response only, moderation. The moderation response message.', classes: [AiProviderResponseBaseEvent::class]),
      new Token(name: 'exception_class', description: 'Failed event only. FQCN of the exception that was thrown.', classes: [AiExceptionEvent::class]),
      new Token(name: 'exception_message', description: 'Failed event only. Exception message.', classes: [AiExceptionEvent::class]),
      new Token(name: 'exception_code', description: 'Failed event only. Exception code.', classes: [AiExceptionEvent::class]),
      new Token(name: 'exception_file', description: 'Failed event only. File where the exception originated.', classes: [AiExceptionEvent::class]),
      new Token(name: 'exception_line', description: 'Failed event only. Line where the exception originated.', classes: [AiExceptionEvent::class]),
      new Token(name: 'is_rate_limit', description: 'Failed event only. 1 when the exception is an AiRateLimitException.', classes: [AiExceptionEvent::class]),
      new Token(name: 'is_quota', description: 'Failed event only. 1 when the exception is an AiQuotaException.', classes: [AiExceptionEvent::class]),
      new Token(name: 'is_unsafe_prompt', description: 'Failed event only. 1 when the exception is an AiUnsafePromptException.', classes: [AiExceptionEvent::class]),
      new Token(name: 'is_missing_feature', description: 'Failed event only. 1 when the exception is an AiMissingFeatureException.', classes: [AiExceptionEvent::class]),
      new Token(name: 'is_bad_request', description: 'Failed event only. 1 when the exception is an AiBadRequestException.', classes: [AiExceptionEvent::class]),
      new Token(name: 'is_response_error', description: 'Failed event only. 1 when the exception is an AiResponseErrorException.', classes: [AiExceptionEvent::class]),
      new Token(name: 'is_request_error', description: 'Failed event only. 1 when the exception is an AiRequestErrorException.', classes: [AiExceptionEvent::class]),
    ],
  )]
  protected function buildEventData(): array {
    $data = [
      'machine_name' => $this->eventName(),
    ];
    if ($this->event) {
      $data += $this->tokenBuilder->build($this->event);
    }
    return $data;
  }

}
