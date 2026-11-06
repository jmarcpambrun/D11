<?php

declare(strict_types=1);

namespace Drupal\ai_search\Plugin\AiFunctionCall;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Enum\EmbeddingStrategyIndexingOptions;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;
use Drupal\ai_search\Trait\DrupalEntityIdParserTrait;
use Drupal\ai_search\Plugin\AiFunctionCall\Derivative\FilterableRagToolDeriver;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Per-index RAG search with optional attribute-based metadata filtering.
 *
 * A deriver creates one derivative per ai_search.index.* config. Each
 * derivative is scoped to a single Search API index and exposes filterable
 * attribute fields as optional context_definitions. Modules can refine
 * definitions via hook_ai_function_call_info_alter() and modify the query
 * via hook_ai_search_filterable_rag_query_alter().
 */
#[FunctionCall(
  id: 'ai_search:filterable_rag_search',
  function_name: 'ai_search_filterable_rag_search',
  name: 'Filterable RAG Search',
  description: 'Searches a specific RAG index with optional attribute filtering.',
  group: 'information_tools',
  deriver: FilterableRagToolDeriver::class,
)]
class FilterableRagTool extends FunctionCallBase implements StructuredExecutableFunctionCallInterface {

  use DrupalEntityIdParserTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * The search results.
   *
   * @var array
   */
  protected array $results = [];

  /**
   * The index ID (derivative ID).
   *
   * @var string
   */
  protected string $indexId = '';

  /**
   * The search string.
   *
   * @var string
   */
  protected string $searchString = '';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->logger = $container->get('logger.factory')->get('ai_search');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $this->indexId = $this->pluginDefinition['index_id'];
    $this->searchString = $this->getContextValue('search_string');
    $this->results = [];
    $amount = (int) $this->getContextValue('amount');
    $min_score = (float) $this->getContextValue('min_score');

    $index = $this->entityTypeManager->getStorage('search_api_index')->load($this->indexId);
    if (!$index instanceof IndexInterface) {
      $this->setOutput("The index was not found.");
      return;
    }

    try {
      $query = $this->buildQuery($index, $this->searchString, $amount);
      $this->moduleHandler->alter('ai_search_filterable_rag_query', $query, $this);

      $results = $query->execute();
      $end_results = [];
      $i = 1;
      foreach ($results->getResultItems() as $result) {
        if ($min_score > $result->getScore()) {
          continue;
        }
        $content = (string) $result->getExtraData('content');
        $end_results[] = "Search result: #$i:\n```\n" . $content . "\n```\n\n";
        [$entity_type, $entity_id, $langcode] = $this->parseDrupalEntityId((string) $result->getExtraData('drupal_entity_id'));
        $this->results[] = [
          'entity_type' => $entity_type,
          'entity_id' => $entity_id,
          'langcode' => $langcode,
          'score' => $result->getScore(),
          'content' => $content,
        ];
        $i++;
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Filterable RAG search failed for index "@index": @message', [
        '@index' => $this->indexId,
        '@message' => $e->getMessage(),
      ]);
      $this->setOutput("Failed to search the index");
      return;
    }

    if (count($end_results)) {
      $output = "Results from searching in the rag index " . $this->indexId . " for the following prompt: " . $this->searchString . ".\n";
      $output .= implode("\n", $end_results);
      $this->setOutput($output);
    }
    else {
      $this->setOutput("No results were found when searching in the rag index " . $this->indexId . " for the following prompt: " . $this->searchString . ".\n");
    }
  }

  /**
   * Builds the Search API query.
   *
   * Builds the query and applies attribute filters.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index entity.
   * @param string $search_string
   *   The search string.
   * @param int $amount
   *   The maximum number of results.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The prepared query, ready for filters and execution.
   */
  protected function buildQuery(IndexInterface $index, string $search_string, int $amount): QueryInterface {
    $query = $index->query([
      'limit' => $amount,
    ]);
    $query->setOption('search_api_ai_get_chunks_result', TRUE);
    $query->addTag('ai_search_rag');
    $query->keys($search_string);
    $this->applyAttributeFilters($query);
    return $query;
  }

  /**
   * Applies attribute-based filters to the query from context values.
   *
   * Reads the ai_search.index.* config for this derivative's index, finds
   * fields with indexing_option "attributes", and adds a query condition for
   * each one that has a non-empty context value.
   *
   * Override this method to customize filter logic (e.g. different operators,
   * conditional filtering).
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query to add conditions to.
   */
  protected function applyAttributeFilters(QueryInterface $query): void {
    $config = $this->configFactory->get('ai_search.index.' . $this->indexId);
    $indexing_options = $config->get('indexing_options') ?? [];
    $context_definitions = $this->getContextDefinitions();

    foreach ($indexing_options as $field_id => $options) {
      if (($options['indexing_option'] ?? '') !== EmbeddingStrategyIndexingOptions::Attributes->value) {
        continue;
      }
      if (!isset($context_definitions[$field_id])) {
        continue;
      }
      $value = $this->getContextValue($field_id);
      if ($value !== NULL && $value !== '') {
        $operator_key = $field_id . '_operator';
        $operator = '=';
        if (isset($context_definitions[$operator_key])) {
          $candidate = $this->getContextValue($operator_key);
          $constraints = $context_definitions[$operator_key]->getConstraints();
          $allowed_operators = $constraints['Choice']['choices'] ?? [];
          if ($candidate && in_array($candidate, $allowed_operators, TRUE)) {
            $operator = $candidate;
          }
        }
        $query->addCondition($field_id, $value, $operator);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStructuredOutput(): array {
    return [
      'results' => $this->results,
      'index' => $this->indexId,
      'search_string' => $this->searchString,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setStructuredOutput(array $output): void {
    $this->results = $output['results'];
    $this->indexId = $output['index'] ?? '';
    $this->searchString = $output['search_string'] ?? '';
  }

}
