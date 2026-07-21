<?php

namespace Drupal\maestro_ai_task_assistant;

use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;

/**
 * Normalizes an AiAssistantApiRunner::process() result into a plain string.
 * 
 * The AiAssistantApiRunner::process() can return a ChatOutput or a decoded JSON
 * array. This normalizer is a simple way to guarantee that the output we use in
 * the Maestro Assistant Capability is what we expect it to be.
 */
class ResponseNormalizer {

  /**
   * Normalize a ChatOutput or decoded-JSON array into a single string.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput|array $response
   *   The value returned by AiAssistantApiRunner::process().
   *
   * @return string
   *   A plain string safe to hand to Maestro's existing return-format
   *   and process-variable/AI-storage handling, unchanged from today's
   *   MaestroAiTaskChatCapability behaviour.
   */
  public static function normalize(ChatOutput|array $response): string {
    if (is_array($response)) {
      return Json::encode($response);
    }
    return Xss::filter($response->getNormalized()->getText());
  }

}
