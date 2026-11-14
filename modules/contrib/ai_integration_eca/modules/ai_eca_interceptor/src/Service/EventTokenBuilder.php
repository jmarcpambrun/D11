<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Service;

use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\ai\Event\AiProviderResponseBaseEvent;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiMissingFeatureException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\Moderation\ModerationOutput;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\OperationType\Summarization\SummarizationInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Builds a token-friendly representation of AI events.
 */
class EventTokenBuilder {

  /**
   * Build token data from an AI event.
   */
  public function build(Event $event): array {
    $data = [];
    if ($event instanceof AiProviderRequestBaseEvent) {
      $data += [
        'request_thread_id' => $event->getRequestThreadId(),
        'request_parent_id' => (string) ($event->getRequestParentId() ?? ''),
        'provider_id' => $event->getProviderId(),
        'model_id' => $event->getModelId(),
        'operation_type' => $event->getOperationType(),
        'tags' => $event->getTags(),
        'tag_count' => count($event->getTags()),
        'configuration' => $event->getConfiguration(),
        'debug_data' => $event->getDebugData(),
        'metadata' => $event->getAllMetadata(),
        'is_chat' => $event->getOperationType() === 'chat' ? 1 : 0,
        'input' => $this->inputToArray($event->getInput()),
        'input_text' => $this->inputText($event->getInput()),
        'input_class' => $event->getInput() ? get_class($event->getInput()) : '',
      ] + $this->chatInputTokens($event->getInput());
    }

    if ($event instanceof AiProviderResponseBaseEvent) {
      $output = $event->getOutput();
      $data += $this->outputToTokens($output);
    }

    // AiExceptionEvent extends AiProviderRequestBaseEvent, so the request
    // branch above already populated provider/model/operation/input data. Here
    // we only add the exception-specific tokens. getException() returns the
    // possibly message-rewritten exception.
    if ($event instanceof AiExceptionEvent) {
      $data += $this->exceptionToTokens($event->getException());
    }

    return $data;
  }

  /**
   * Convert a Throwable into token data.
   */
  public function exceptionToTokens(\Throwable $e): array {
    return [
      'exception_class' => get_class($e),
      'exception_message' => $e->getMessage(),
      'exception_code' => $e->getCode(),
      'exception_file' => $e->getFile(),
      'exception_line' => $e->getLine(),
      'is_rate_limit' => $e instanceof AiRateLimitException ? 1 : 0,
      'is_quota' => $e instanceof AiQuotaException ? 1 : 0,
      'is_unsafe_prompt' => $e instanceof AiUnsafePromptException ? 1 : 0,
      'is_missing_feature' => $e instanceof AiMissingFeatureException ? 1 : 0,
      'is_bad_request' => $e instanceof AiBadRequestException ? 1 : 0,
      'is_response_error' => $e instanceof AiResponseErrorException ? 1 : 0,
      'is_request_error' => $e instanceof AiRequestErrorException ? 1 : 0,
    ];
  }

  /**
   * Build chat-request specific tokens (tools, schema, streamed).
   *
   * These expose chat request properties so ECA models can branch on them with
   * generic comparison conditions, instead of bespoke condition plugins. The
   * keys are always present (with empty/zero defaults for non-chat input) so
   * token availability is consistent across request events.
   */
  public function chatInputTokens(mixed $input): array {
    $defaults = [
      'chat_tools' => [],
      'chat_tool_count' => 0,
      'chat_tool_names' => [],
      'chat_has_tools' => 0,
      'chat_has_schema' => 0,
      'chat_streamed' => 0,
    ];
    if (!$input instanceof ChatInput) {
      return $defaults;
    }

    $tools = $input->getChatTools();
    if ($tools !== NULL) {
      $names = [];
      foreach ($tools->getFunctions() as $function) {
        $names[] = (string) $function->getName();
      }
      try {
        $defaults['chat_tools'] = $tools->renderToolsArray();
      }
      catch (\Throwable) {
        // Best-effort: an incomplete tools definition should not break token
        // building. The count/names below are still useful.
        $defaults['chat_tools'] = [];
      }
      $defaults['chat_tool_names'] = $names;
      $defaults['chat_tool_count'] = count($tools->getFunctions());
      $defaults['chat_has_tools'] = $defaults['chat_tool_count'] > 0 ? 1 : 0;
    }
    $defaults['chat_has_schema'] = !empty($input->getChatStructuredJsonSchema()) ? 1 : 0;
    $defaults['chat_streamed'] = $input->isStreamedOutput() ? 1 : 0;

    return $defaults;
  }

  /**
   * Convert input to array.
   */
  public function inputToArray(mixed $input): array {
    if ($input instanceof InputInterface) {
      try {
        return (array) $input->toArray();
      }
      catch (\Throwable) {
        return [];
      }
    }
    if (is_array($input)) {
      return $input;
    }
    return [];
  }

  /**
   * Extract input text.
   */
  public function inputText(mixed $input): string {
    if ($input instanceof ChatInput) {
      $parts = [];
      foreach ($input->getMessages() as $m) {
        if ($m instanceof ChatMessage) {
          $parts[] = $m->getText();
        }
      }
      return implode("\n", $parts);
    }
    if ($input instanceof SummarizationInput) {
      return (string) $input->getText();
    }
    if ($input instanceof TranslateTextInput) {
      return (string) $input->getText();
    }
    if ($input instanceof InputInterface) {
      return (string) $input->toString();
    }
    if (is_string($input)) {
      return $input;
    }
    return '';
  }

  /**
   * Convert an Output object to token-friendly data.
   */
  public function outputToTokens(?OutputInterface $output): array {
    if ($output === NULL) {
      return [
        'output' => [],
        'output_text' => '',
        'output_class' => '',
        'output_normalized' => NULL,
        'output_raw' => NULL,
        'output_metadata' => [],
      ];
    }

    $data = [
      'output_class' => get_class($output),
      'output_raw' => $output->getRawOutput(),
      'output_metadata' => $output->getMetadata() ?: [],
    ];

    $normalized = $output->getNormalized();
    $data['output_normalized'] = $this->normalizedToArray($normalized);
    $data['output_text'] = $this->normalizedToText($normalized);

    if ($output instanceof ChatOutput) {
      // getTokenUsage()/getRateLimits() return non-nullable DTOs; their int
      // fields are nullable, hence the ?? 0 fallbacks.
      $usage = $output->getTokenUsage();
      $data['token_usage'] = [
        'input' => $usage->input ?? 0,
        'output' => $usage->output ?? 0,
        'total' => $usage->total ?? 0,
        'reasoning' => $usage->reasoning ?? 0,
        'cached' => $usage->cached ?? 0,
      ];
      $limits = $output->getRateLimits();
      $data['rate_limits'] = [
        'max_requests' => $limits->rateLimitMaxRequests ?? 0,
        'max_tokens' => $limits->rateLimitMaxTokens ?? 0,
        'remaining_requests' => $limits->rateLimitRemainingRequests ?? 0,
        'remaining_tokens' => $limits->rateLimitRemainingTokens ?? 0,
        'reset_requests' => $limits->rateLimitResetRequests ?? 0,
        'reset_tokens' => $limits->rateLimitResetTokens ?? 0,
      ];
      $data['output_is_streamed'] = $normalized instanceof StreamedChatMessageIteratorInterface ? 1 : 0;
    }

    if ($output instanceof ModerationOutput) {
      $resp = $output->getNormalized();
      $data['moderation_flagged'] = (int) $resp->isFlagged();
      $data['moderation_message'] = (string) $resp->getMessage();
    }

    $data['output'] = is_array($data['output_normalized']) ? $data['output_normalized'] : [];
    return $data;
  }

  /**
   * Normalize a generic "normalized" output value to a text string.
   */
  public function normalizedToText(mixed $normalized): string {
    if ($normalized instanceof ChatMessage) {
      return $normalized->getText();
    }
    if (is_string($normalized)) {
      return $normalized;
    }
    if (is_scalar($normalized)) {
      return (string) $normalized;
    }
    return '';
  }

  /**
   * Normalize a "normalized" output value to array.
   */
  public function normalizedToArray(mixed $normalized): mixed {
    if ($normalized instanceof ChatMessage) {
      return [
        'role' => $normalized->getRole(),
        'text' => $normalized->getText(),
      ];
    }
    if ($normalized instanceof StreamedChatMessageIteratorInterface) {
      return ['streamed' => TRUE];
    }
    if ($normalized instanceof ImageFile) {
      return [
        'filename' => $normalized->getFilename(),
        'mime_type' => $normalized->getMimeType(),
      ];
    }
    if (is_array($normalized)) {
      return $normalized;
    }
    if (is_scalar($normalized)) {
      return (string) $normalized;
    }
    if (is_object($normalized) && method_exists($normalized, 'toArray')) {
      return (array) $normalized->toArray();
    }
    return NULL;
  }

}
