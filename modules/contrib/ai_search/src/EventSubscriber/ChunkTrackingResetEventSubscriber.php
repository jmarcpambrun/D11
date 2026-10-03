<?php

namespace Drupal\ai_search\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\search_api\Event\ReindexScheduledEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resets chunk-slicing progress when an index is cleared or reindexed.
 *
 * The AI Search backend tracks per-item embedding progress via the
 * search_api_item.processed_chunks / total_chunks columns (see
 * SearchApiAiSearchBackend::indexItemsWithChunkSlicing()). Neither
 * "Clear index" nor `drush search-api:reset-tracker` resets those columns
 * by default: both ultimately call the index's tracker plugin, and the
 * chunk columns are only reset there if the site has explicitly configured
 * the "AI Search Chunked Tracker" (ai_search_tracker) plugin. Most indexes
 * use the default "Basic" tracker, which has no notion of these columns.
 *
 * Left stale, a previously fully-indexed item's processed_chunks can equal
 * total_chunks after a clear. The next indexing run then computes a chunk
 * offset equal to total_chunks, array_slice() returns nothing, and the item
 * is silently marked "successfully indexed" while zero embeddings are
 * written to the vector store.
 *
 * Both Index::clear() and Index::reindex() dispatch
 * SearchApiEvents::REINDEX_SCHEDULED regardless of which tracker plugin is
 * configured, so listening here fixes both paths without depending on the
 * tracker choice.
 *
 * Known limitation: ReindexScheduledEvent does not expose which datasource
 * triggered the event, so the reset here is index-wide rather than scoped
 * to a single datasource. On a multi-datasource index, clearing or
 * resetting the tracker for one datasource also resets chunk-tracking
 * progress for items belonging to the index's other datasources. This is
 * safe (it only causes those items to be fully re-chunked on their next
 * indexing pass) but is not the tightest possible scope.
 *
 * @see \Drupal\ai_search\Plugin\search_api\backend\SearchApiAiSearchBackend::indexItemsWithChunkSlicing()
 * @see \Drupal\ai_search\Plugin\search_api\tracker\AiSearchTracker
 */
class ChunkTrackingResetEventSubscriber implements EventSubscriberInterface {

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    protected readonly Connection $database,
  ) {
  }

  /**
   * Resets chunk-tracking columns for AI Search backed indexes.
   *
   * @param \Drupal\search_api\Event\ReindexScheduledEvent $event
   *   The reindex scheduled event.
   */
  public function onReindexScheduled(ReindexScheduledEvent $event): void {
    $index = $event->getIndex();
    $server = $index->getServerInstanceIfAvailable();
    $backend_id = $server?->getBackend()?->getPluginId();
    if ($backend_id !== 'search_api_ai_search') {
      return;
    }

    // The chunk-tracking columns are added by ai_search on install; guard
    // against sites where search_api_item does not have them yet (e.g. an
    // update path race, or an index whose tables were never created).
    if (!$this->database->schema()->fieldExists('search_api_item', 'processed_chunks')) {
      return;
    }

    $this->database->update('search_api_item')
      ->fields([
        'total_chunks' => NULL,
        'processed_chunks' => 0,
      ])
      ->condition('index_id', $index->id())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::REINDEX_SCHEDULED => 'onReindexScheduled',
    ];
  }

}
