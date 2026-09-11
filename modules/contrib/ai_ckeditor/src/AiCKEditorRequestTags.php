<?php

declare(strict_types=1);

namespace Drupal\ai_ckeditor;

/**
 * Provider request tags for CKEditor chat calls.
 *
 * Shared so AiRequest and the consumer type stay in sync. This class
 * lives outside Plugin/AiContextConsumerType so the controller can
 * tag chat() without loading the consumer type (that class depends
 * on AI Context). Context consumer IDs are a different string:
 * ckeditor:{plugin_id}.
 */
final class AiCKEditorRequestTags {

  /**
   * Coarse tag that makes the CKEditor consumer type eligible.
   */
  public const ROUTING = 'ai_ckeditor';

  /**
   * Prefix for the per-plugin instance provider request tag.
   */
  public const INSTANCE_PREFIX = 'ai_ckeditor:';

  /**
   * Help plugin ID. It never calls the provider.
   */
  public const HELP_PLUGIN_ID = 'ai_ckeditor_help';

  /**
   * Builds the chat() provider request tags for a plugin.
   *
   * @param string $pluginId
   *   The AiCKEditor plugin ID.
   *
   * @return string[]
   *   Provider request tags.
   */
  public static function forPlugin(string $pluginId): array {
    return [
      self::ROUTING,
      self::INSTANCE_PREFIX . $pluginId,
    ];
  }

}
