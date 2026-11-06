<?php

declare(strict_types=1);

namespace Drupal\ai_search\Plugin\AiFunctionCall\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Enum\EmbeddingStrategyIndexingOptions;
use Drupal\search_api\IndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives a FilterableRagTool plugin for each AI Search index.
 *
 * Each derivative is scoped to a single search_api_index and exposes any
 * fields marked as "attributes" in the ai_search.index.* config as optional
 * context_definitions, enabling the LLM to pass filter values.
 */
class FilterableRagToolDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  protected const FUNCTION_NAME_PREFIX = 'ai_search_filterable_rag_search__';

  protected const MAX_FUNCTION_NAME_LENGTH = 64;

  protected const FUNCTION_NAME_HASH_LENGTH = 12;

  /**
   * Constructs a FilterableRagToolDeriver object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new self(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!empty($this->derivatives)) {
      return parent::getDerivativeDefinitions($base_plugin_definition);
    }

    $definitions = [];
    $config_names = $this->configFactory->listAll('ai_search.index.');

    foreach ($config_names as $config_name) {
      $index_id = substr($config_name, strlen('ai_search.index.'));
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($index_id);
      if (!$index instanceof IndexInterface) {
        continue;
      }

      $config = $this->configFactory->get($config_name);
      $indexing_options = $config->get('indexing_options') ?? [];

      $definition = $base_plugin_definition;
      $definition['id'] = 'ai_search:filterable_rag_search:' . $index_id;
      $definition['index_id'] = $index_id;
      $definition['function_name'] = $this->buildFunctionName($index_id);
      $definition['name'] = $this->t('Filterable RAG Search: @label', ['@label' => $index->label()]);
      $definition['description'] = $this->t("Searches the '@label' index for relevant content. Supports optional attribute filters to narrow results.", ['@label' => $index->label()]);
      $definition['group'] = 'information_tools';
      $definition['context_definitions'] = $this->buildContextDefinitions($index, $indexing_options);

      $definitions[$index_id] = $definition;
    }

    $this->derivatives = $definitions;
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Builds context definitions for a given index.
   *
   * Always includes search_string, amount, and min_score. Adds one optional
   * context_definition per field marked as "attributes" in the index config.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   * @param array $indexing_options
   *   The indexing_options from the ai_search.index.* config.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinition[]
   *   An array of context definitions keyed by name.
   */
  protected function buildContextDefinitions(IndexInterface $index, array $indexing_options): array {
    $context_definitions = [
      'search_string' => new ContextDefinition(
        data_type: 'string',
        label: new TranslatableMarkup('Search String'),
        required: TRUE,
        description: new TranslatableMarkup('The search string to search for.'),
      ),
      'amount' => new ContextDefinition(
        data_type: 'integer',
        label: new TranslatableMarkup('Amount'),
        required: FALSE,
        description: new TranslatableMarkup('The amount of results to find.'),
        default_value: 10,
      ),
      'min_score' => new ContextDefinition(
        data_type: 'float',
        label: new TranslatableMarkup('Minimal Score'),
        required: FALSE,
        description: new TranslatableMarkup('The minimal score threshold to pass.'),
        default_value: 0.5,
      ),
    ];

    $index_fields = $index->getFields();
    foreach ($indexing_options as $field_id => $options) {
      if (($options['indexing_option'] ?? '') !== EmbeddingStrategyIndexingOptions::Attributes->value) {
        continue;
      }
      if (!isset($index_fields[$field_id])) {
        continue;
      }

      $field = $index_fields[$field_id];
      $search_api_type = $field->getType();
      $data_type = $this->mapSearchApiType($search_api_type);
      $context_definitions[$field_id] = new ContextDefinition(
        data_type: $data_type,
        label: $field->getLabel(),
        required: FALSE,
        description: new TranslatableMarkup('Filter by @label.', ['@label' => $field->getLabel()]),
      );

      $allowed_operators = $this->getAllowedOperators($search_api_type);
      if (count($allowed_operators) > 1) {
        $context_definitions[$field_id . '_operator'] = new ContextDefinition(
          data_type: 'string',
          label: new TranslatableMarkup('@label operator', ['@label' => $field->getLabel()]),
          required: FALSE,
          description: new TranslatableMarkup('Comparison operator for the @label filter.', ['@label' => $field->getLabel()]),
          default_value: '=',
          constraints: ['Choice' => ['choices' => $allowed_operators]],
        );
      }
    }

    return $context_definitions;
  }

  /**
   * Builds a provider-compatible function name for an index.
   *
   * @param string $index_id
   *   The Search API index ID.
   *
   * @return string
   *   A function name no longer than 64 characters.
   */
  protected function buildFunctionName(string $index_id): string {
    $normalized_index_id = str_replace('-', '_', $index_id);
    $maximum_index_id_length = self::MAX_FUNCTION_NAME_LENGTH - strlen(self::FUNCTION_NAME_PREFIX);
    if (strlen($normalized_index_id) > $maximum_index_id_length) {
      $suffix = '_' . substr(hash('sha256', $index_id), 0, self::FUNCTION_NAME_HASH_LENGTH);
      $normalized_index_id = substr($normalized_index_id, 0, $maximum_index_id_length - strlen($suffix)) . $suffix;
    }

    return self::FUNCTION_NAME_PREFIX . $normalized_index_id;
  }

  /**
   * Maps a Search API field type to a context definition data type.
   *
   * @param string $type
   *   The Search API field type (e.g., 'string', 'integer', 'date').
   *
   * @return string
   *   The context definition data type.
   */
  protected function mapSearchApiType(string $type): string {
    return match ($type) {
      'integer' => 'integer',
      'decimal' => 'float',
      'boolean' => 'boolean',
      default => 'string',
    };
  }

  /**
   * Returns the allowed comparison operators for a Search API field type.
   *
   * @param string $search_api_type
   *   The raw Search API field type (e.g., 'integer', 'string', 'date').
   *
   * @return string[]
   *   The allowed operator strings.
   */
  protected function getAllowedOperators(string $search_api_type): array {
    return match ($search_api_type) {
      'integer', 'decimal', 'date' => ['=', '<>', '<', '<=', '>=', '>'],
      'boolean' => ['='],
      default => ['=', '<>'],
    };
  }

}
