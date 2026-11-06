<?php

/**
 * @file
 * Hooks provided by the AI search module.
 */

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\ai_search\Plugin\AiFunctionCall\FilterableRagTool;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Allows other modules to alter or re-rank AI search results.
 *
 * Invoked after AI search results are generated but before they are returned.
 * Implementations can adjust relevance scores, filter results, or change order.
 *
 * @param array &$results
 *   An associative array of entity IDs and their relevance scores.
 *   Keys look like "entity:node/123:en", and values are float scores.
 * @param string|array $keywords
 *   The search keywords used for the AI query.
 * @param \Drupal\search_api\IndexInterface $ai_search_index
 *   The AI Search Index.
 * @param \Drupal\search_api\IndexInterface $target_index
 *   The Search API Index being boosted.
 *
 * @see \Drupal\ai_search\Plugin\search_api\processor\BoostByAiSearchBase::getAiSearchResults()
 */
function hook_ai_search_boost_results_alter(array &$results, mixed $keywords, IndexInterface $ai_search_index, IndexInterface $target_index): void {
  // Example 1: Boost a specific node manually.
  if (isset($results['entity:node/8215:en'])) {
    $results['entity:node/8215:en'] += 0.2;
  }

  // Example 2: Boost all "article" nodes slightly.
  foreach ($results as $entity_id => $score) {
    if (preg_match('/^entity:node\/(\d+)/', $entity_id, $matches)) {
      $nid = (int) $matches[1];
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($nid);

      if ($node && $node->bundle() === 'article') {
        $results[$entity_id] = $score + 0.05;
      }
    }
  }

  // Sort the results by updated score in descending order.
  arsort($results);
}

/**
 * Alter the Search API query before execution in FilterableRagTool.
 *
 * Called after attribute filters have been applied as query conditions but
 * before the query is executed. Use this to add complex condition groups,
 * sorts, or provider-specific query options.
 *
 * @param \Drupal\search_api\Query\QueryInterface $query
 *   The Search API query object, with attribute conditions already applied.
 * @param \Drupal\ai_search\Plugin\AiFunctionCall\FilterableRagTool $plugin
 *   The FilterableRagTool plugin instance. Use $plugin->getDerivativeId()
 *   to get the index ID, or $plugin->getContextValue() to read context
 *   values provided by the LLM.
 */
function hook_ai_search_filterable_rag_query_alter(QueryInterface &$query, FilterableRagTool $plugin) {
}

/**
 * @} End of "addtogroup hooks".
 */
