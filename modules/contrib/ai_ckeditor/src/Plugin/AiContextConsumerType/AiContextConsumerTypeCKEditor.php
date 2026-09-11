<?php

declare(strict_types=1);

namespace Drupal\ai_ckeditor\Plugin\AiContextConsumerType;

use Drupal\ai_ckeditor\AiCKEditorRequestTags;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Drupal\ai_context\Attribute\AiContextConsumerType;
use Drupal\ai_context\Model\AiContextConsumerId;
use Drupal\ai_context\Model\AiContextConsumerInstance;
use Drupal\ai_context\Model\AiContextConsumerInstanceCollection;
use Drupal\ai_context\Model\AiContextProviderRequestContext;
use Drupal\ai_context\Plugin\AiContextConsumerType\AiContextConsumerTypeBase;
use Drupal\ai_context\Service\AiContextConsumerRouter;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor context consumer type.
 *
 * Lists each AiCKEditor tool that calls a provider as a Context
 * Control Center consumer. Automatic context push stays off until
 * an admin turns it on for that tool. A request is matched only by
 * the ai_ckeditor:{plugin_id} provider request tag, not by the text
 * format. Context consumer IDs use the short type prefix ckeditor:
 * (same pattern as agent: and automator:). Provider request tags
 * use the module name ai_ckeditor. A tool appears on Context
 * consumers only when at least one enabled text format has
 * it enabled. Disabled formats do not count. The consumer
 * description names those formats. Consumer names link to
 * the Text formats and editors page, or to the one format
 * that has that tool enabled.
 */
#[AiContextConsumerType(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('CKEditor'),
  description: new TranslatableMarkup(
    'AI CKEditor tools that receive pushed context in their system prompt.',
  ),
)]
final class AiContextConsumerTypeCKEditor extends AiContextConsumerTypeBase {

  /**
   * The consumer type plugin ID.
   */
  public const PLUGIN_ID = 'ckeditor';

  /**
   * Module that must be enabled for this type to be available.
   *
   * This plugin is owned by ai_ckeditor. AI Context only provides
   * the consumer type API.
   */
  public const REQUIRED_MODULE = 'ai_context';

  /**
   * Oldest AI Context release that ships this consumer type API.
   */
  public const MINIMUM_AI_CONTEXT_VERSION = '1.0.0-beta5';

  /**
   * CKEditor 5 plugin ID stored on editor config.
   */
  private const EDITOR_PLUGIN_ID = 'ai_ckeditor_ai';

  /**
   * The CKEditor plugin manager.
   */
  private AiCKEditorPluginManager $ckeditorPluginManager;

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   */
  private ModuleHandlerInterface $moduleHandler;

  /**
   * The module extension list.
   */
  private ModuleExtensionList $moduleExtensionList;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $instance->ckeditorPluginManager = $container
      ->get('plugin.manager.ai_ckeditor');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->moduleExtensionList = $container
      ->get('extension.list.module');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelRoute(string $instanceId): ?array {
    if (!$this->isChatTool($instanceId)) {
      return NULL;
    }

    $format_ids = $this->getFormatIdsByEnabledTool()[$instanceId] ?? [];
    if (count($format_ids) === 1) {
      return [
        'route_name' => 'entity.filter_format.edit_form',
        'route_parameters' => [
          'filter_format' => $format_ids[0],
        ],
      ];
    }

    return [
      'route_name' => 'filter.admin_overview',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceDefaults(): array {
    return [
      'push_enabled' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    if (!$this->moduleHandler->moduleExists(self::REQUIRED_MODULE)) {
      return FALSE;
    }

    $info = $this->moduleExtensionList->getExtensionInfo(
      self::REQUIRED_MODULE,
    );
    $version = '';
    if (isset($info['version']) && is_string($info['version'])) {
      $version = $info['version'];
    }

    return self::isSupportedAiContextVersion($version);
  }

  /**
   * Whether an AI Context version is new enough for this type.
   *
   * Empty and branch-dev versions are treated as current. Packaged
   * versions must be 1.0.0-beta5 or later.
   *
   * @param string $version
   *   The version from the AI Context extension info, or an empty
   *   string when the checkout is unpackaged.
   *
   * @return bool
   *   TRUE when this consumer type can run against that version.
   */
  public static function isSupportedAiContextVersion(string $version): bool {
    $version = trim($version);
    if ($version === '') {
      return TRUE;
    }
    if (preg_match('/^\d+(\.\d+)?\.x-dev$/', $version) === 1) {
      return TRUE;
    }

    $comparable = explode('+', $version, 2)[0];
    return version_compare(
      $comparable,
      self::MINIMUM_AI_CONTEXT_VERSION,
      '>=',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutingRequestTags(): array {
    return [AiCKEditorRequestTags::ROUTING];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(): AiContextConsumerInstanceCollection {
    $cache = new CacheableMetadata();
    $cache->addCacheTags([
      'config:core.extension',
      'ai_ckeditor_plugins',
    ]);
    // Listing rows, descriptions, and label links depend on
    // enabled tools and format labels.
    if ($this->entityTypeManager->hasDefinition('editor')) {
      $cache->addCacheTags(
        $this->entityTypeManager
          ->getDefinition('editor')
          ->getListCacheTags(),
      );
    }
    if ($this->entityTypeManager->hasDefinition('filter_format')) {
      $cache->addCacheTags(
        $this->entityTypeManager
          ->getDefinition('filter_format')
          ->getListCacheTags(),
      );
    }
    if (!$this->isAvailable()) {
      return new AiContextConsumerInstanceCollection([], $cache);
    }

    $enabled_tools = $this->getFormatIdsByEnabledTool();
    $labels_by_id = $this->loadFormatLabels(
      $this->uniqueFormatIds($enabled_tools),
    );
    $instances = [];
    foreach ($this->ckeditorPluginManager->getDefinitions() as $id => $definition) {
      $id = (string) $id;
      if (!$this->isChatTool($id)) {
        continue;
      }
      if (!isset($enabled_tools[$id])) {
        continue;
      }
      $instances[] = new AiContextConsumerInstance(
        AiContextConsumerId::fromParts(self::PLUGIN_ID, $id),
        (string) ($definition['label'] ?? $id),
        $this->buildInstanceDescription(
          isset($definition['description'])
            ? (string) $definition['description']
            : '',
          $enabled_tools[$id],
          $labels_by_id,
        ),
      );
    }

    return new AiContextConsumerInstanceCollection($instances, $cache);
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConsumerId(
    AiContextProviderRequestContext $providerRequestContext,
  ): ?AiContextConsumerId {
    if (!$this->isAvailable()) {
      return NULL;
    }

    $tags = $providerRequestContext->getRequestTags();
    if (AiContextConsumerRouter::hasExcludedRequestTag($tags)) {
      return NULL;
    }

    $instance_id = $this->instanceIdFromRequestTags($tags);
    if ($instance_id === NULL) {
      return NULL;
    }

    if (!$this->instanceExists($instance_id)) {
      return NULL;
    }

    return AiContextConsumerId::fromParts(self::PLUGIN_ID, $instance_id);
  }

  /**
   * Returns the plugin ID from provider request tags, if unique.
   *
   * Only ai_ckeditor:{plugin_id} is used. The coarse ai_ckeditor
   * tag is ignored. A text format can enable several tools, so the
   * format is not used to pick a consumer.
   *
   * @param string[] $requestTags
   *   Provider request tags.
   *
   * @return string|null
   *   The instance ID, or NULL when missing, empty, or ambiguous.
   */
  private function instanceIdFromRequestTags(array $requestTags): ?string {
    $ids = [];
    $prefix = AiCKEditorRequestTags::INSTANCE_PREFIX;
    foreach ($requestTags as $tag) {
      if (!str_starts_with($tag, $prefix)) {
        continue;
      }
      $id = substr($tag, strlen($prefix));
      if ($id === '') {
        continue;
      }
      $ids[$id] = $id;
    }

    if (count($ids) !== 1) {
      return NULL;
    }

    return array_values($ids)[0];
  }

  /**
   * Whether a chat-capable AiCKEditor plugin exists for the instance ID.
   *
   * @param string $instanceId
   *   The AiCKEditor plugin ID.
   *
   * @return bool
   *   TRUE when the plugin exists and is a real chat tool.
   */
  private function instanceExists(string $instanceId): bool {
    if (!$this->isChatTool($instanceId)) {
      return FALSE;
    }

    return $this->ckeditorPluginManager->hasDefinition($instanceId);
  }

  /**
   * Whether the plugin is a chat tool that can receive context.
   *
   * @param string $pluginId
   *   The AiCKEditor plugin ID.
   *
   * @return bool
   *   TRUE when the plugin calls the provider.
   */
  private function isChatTool(string $pluginId): bool {
    return $pluginId !== AiCKEditorRequestTags::HELP_PLUGIN_ID;
  }

  /**
   * Builds a consumer description that names enabled formats.
   *
   * @param string $pluginDescription
   *   The AiCKEditor plugin description.
   * @param string[] $formatIds
   *   Format IDs that have this tool enabled.
   * @param array<string, string> $labelsById
   *   Format labels keyed by format ID.
   *
   * @return string
   *   Plugin description plus the enabled-formats sentence.
   */
  private function buildInstanceDescription(
    string $pluginDescription,
    array $formatIds,
    array $labelsById,
  ): string {
    $parts = [];
    $pluginDescription = trim($pluginDescription);
    if ($pluginDescription !== '') {
      if (!preg_match('/[.!?]$/u', $pluginDescription)) {
        $pluginDescription .= '.';
      }
      $parts[] = $pluginDescription;
    }
    $enabled = $this->buildEnabledFormatsDescription(
      $formatIds,
      $labelsById,
    );
    if ($enabled !== '') {
      $parts[] = $enabled;
    }

    return implode(' ', $parts);
  }

  /**
   * Builds the sentence that lists enabled text formats.
   *
   * @param string[] $formatIds
   *   Format IDs that have this tool enabled.
   * @param array<string, string> $labelsById
   *   Format labels keyed by format ID.
   *
   * @return string
   *   An "Enabled on ..." sentence, or empty when there are no
   *   formats.
   */
  private function buildEnabledFormatsDescription(
    array $formatIds,
    array $labelsById,
  ): string {
    $labels = [];
    foreach ($formatIds as $format_id) {
      $labels[] = $labelsById[$format_id] ?? $format_id;
    }
    $labels = array_values(array_filter(
      $labels,
      static function (string $label): bool {
        return $label !== '';
      },
    ));
    if ($labels === []) {
      return '';
    }
    natcasesort($labels);
    $labels = array_values($labels);

    // Leave @formats in the translated template, then replace it
    // without HTML escaping. The consumers listing escapes the
    // full description; nested t() placeholders would show
    // &quot; instead of quotes.
    $template = (string) $this->formatPlural(
      count($labels),
      'Enabled on @formats text format.',
      'Enabled on @formats text formats.',
    );
    return strtr($template, [
      '@formats' => $this->formatQuotedLabels($labels),
    ]);
  }

  /**
   * Joins format labels with quotes and "and".
   *
   * @param string[] $labels
   *   Format labels, already sorted.
   *
   * @return string
   *   Quoted labels, such as `"Full HTML" and "Restricted HTML"`.
   */
  private function formatQuotedLabels(array $labels): string {
    $quoted = [];
    foreach ($labels as $label) {
      $quoted[] = '"' . $label . '"';
    }
    $count = count($quoted);
    if ($count === 1) {
      return $quoted[0];
    }
    if ($count === 2) {
      return strtr((string) $this->t('@first and @second'), [
        '@first' => $quoted[0],
        '@second' => $quoted[1],
      ]);
    }
    $last = array_pop($quoted);
    return strtr((string) $this->t('@list, and @last'), [
      '@list' => implode(', ', $quoted),
      '@last' => $last,
    ]);
  }

  /**
   * Returns unique format IDs from a tool-to-formats map.
   *
   * @param array<string, string[]> $enabledTools
   *   Format IDs keyed by tool ID.
   *
   * @return string[]
   *   Unique format IDs.
   */
  private function uniqueFormatIds(array $enabledTools): array {
    $ids = [];
    foreach ($enabledTools as $format_ids) {
      foreach ($format_ids as $format_id) {
        $ids[$format_id] = $format_id;
      }
    }

    return array_values($ids);
  }

  /**
   * Loads text format labels keyed by format ID.
   *
   * @param string[] $formatIds
   *   Format IDs to load.
   *
   * @return array<string, string>
   *   Labels keyed by format ID. Missing formats fall back to
   *   the format ID.
   */
  private function loadFormatLabels(array $formatIds): array {
    $labels = [];
    foreach ($formatIds as $format_id) {
      $labels[$format_id] = $format_id;
    }
    if ($formatIds === []
      || !$this->entityTypeManager->hasDefinition('filter_format')) {
      return $labels;
    }

    $formats = $this->entityTypeManager
      ->getStorage('filter_format')
      ->loadMultiple($formatIds);
    foreach ($formatIds as $format_id) {
      $format = $formats[$format_id] ?? NULL;
      if ($format === NULL) {
        continue;
      }
      $label = trim((string) $format->label());
      if ($label !== '') {
        $labels[$format_id] = $label;
      }
    }

    return $labels;
  }

  /**
   * Returns format IDs keyed by enabled AI CKEditor tool ID.
   *
   * Only enabled text formats are included. Disabled formats
   * do not list a tool, name it, or take the unique-format
   * label link. Editor status is not used: a disabled format
   * still has an editor with status TRUE.
   *
   * @return array<string, string[]>
   *   Format IDs per tool, sorted.
   */
  private function getFormatIdsByEnabledTool(): array {
    if (!$this->entityTypeManager->hasDefinition('editor')
      || !$this->entityTypeManager->hasDefinition('filter_format')) {
      return [];
    }

    $enabled_format_ids = $this->entityTypeManager
      ->getStorage('filter_format')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', TRUE)
      ->execute();
    if ($enabled_format_ids === []) {
      return [];
    }

    $map = [];
    $editors = $this->entityTypeManager
      ->getStorage('editor')
      ->loadMultiple($enabled_format_ids);
    foreach ($editors as $editor) {
      if (!$editor instanceof EditorInterface) {
        continue;
      }
      $format_id = (string) $editor->id();
      if ($format_id === '') {
        continue;
      }
      foreach ($this->getEnabledToolIds($editor) as $tool_id) {
        $map[$tool_id][] = $format_id;
      }
    }
    foreach ($map as &$format_ids) {
      $format_ids = array_values(array_unique($format_ids));
      sort($format_ids);
    }

    return $map;
  }

  /**
   * Returns enabled chat-tool IDs for an editor.
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor config entity.
   *
   * @return string[]
   *   Enabled AiCKEditor plugin IDs.
   */
  private function getEnabledToolIds(EditorInterface $editor): array {
    if ($editor->getEditor() !== 'ckeditor5') {
      return [];
    }

    $settings = $editor->getSettings();
    $plugins = $settings['plugins'][self::EDITOR_PLUGIN_ID]['plugins'] ?? [];
    if (!is_array($plugins)) {
      return [];
    }

    $ids = [];
    foreach ($plugins as $tool_id => $tool) {
      if (!is_string($tool_id) || $tool_id === '') {
        continue;
      }
      if (!$this->isChatTool($tool_id)) {
        continue;
      }
      if (!is_array($tool) || empty($tool['enabled'])) {
        continue;
      }
      $ids[] = $tool_id;
    }

    return $ids;
  }

}
