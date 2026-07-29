<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Controller;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\entity_usage\SourceEntityStatus;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\trash\Trash;
use Drupal\trash\TrashManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for our pages.
 */
class ListUsageController extends ControllerBase {

  /**
   * Number of items per page to use when nothing was configured.
   */
  const int ITEMS_PER_PAGE_DEFAULT = 25;

  /**
   * The index for the default revision "group".
   */
  protected const int REVISION_DEFAULT = 0;

  /**
   * The index for the pending revision "group".
   */
  protected const int REVISION_PENDING = 1;

  /**
   * The index for the old revision "group".
   */
  protected const int REVISION_OLD = -1;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The EntityUsage service.
   *
   * @var \Drupal\entity_usage\EntityUsageInterface
   */
  protected $entityUsage;

  /**
   * All displayable usage entries for this target entity, in display order.
   *
   * Each entry is the raw (type, id, records) tuple from
   * \Drupal\entity_usage\EntityUsageInterface::listSources(), filtered down to
   * only the entries that will actually produce a row (i.e. whose source
   * entity exists, and is not a soft-deleted inline block). This filtering is
   * done with lightweight existence/field queries rather than full entity
   * loads, so the (potentially large) full set of source entities never needs
   * to be loaded just to compute the pager total or entry ordering. Only the
   * entries for the page actually being viewed get their source entity fully
   * loaded, in ::buildRows().
   *
   * @var array<int, array{source_type: string, source_id: int|string, records: mixed[]}>
   */
  protected array $displayableEntries;

  /**
   * The Entity Usage settings config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $entityUsageConfig;

  /**
   * The number of records per page this controller should output.
   *
   * @var int
   */
  protected $itemsPerPage;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The inline block usage service.
   *
   * @var \Drupal\layout_builder\InlineBlockUsageInterface|null
   */
  protected $inlineBlockUsage;

  /**
   * The trash manager.
   *
   * @var \Drupal\trash\TrashManagerInterface|null
   */
  protected ?TrashManagerInterface $trashManager;

  /**
   * ListUsageController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\entity_usage\EntityUsageInterface $entity_usage
   *   The EntityUsage service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   * @param \Drupal\layout_builder\InlineBlockUsageInterface|null $inline_block_usage
   *   The inline block usage.
   * @param \Drupal\trash\TrashManagerInterface|null $trash_manager
   *   The trash manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityUsageInterface $entity_usage,
    ConfigFactoryInterface $config_factory,
    PagerManagerInterface $pager_manager,
    ?InlineBlockUsageInterface $inline_block_usage,
    ?TrashManagerInterface $trash_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityUsage = $entity_usage;
    $this->entityUsageConfig = $config_factory->get('entity_usage.settings');
    $this->itemsPerPage = $this->entityUsageConfig->get('usage_controller_items_per_page') ?: self::ITEMS_PER_PAGE_DEFAULT;
    $this->pagerManager = $pager_manager;
    $this->inlineBlockUsage = $inline_block_usage;
    $this->trashManager = $trash_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_usage.usage'),
      $container->get('config.factory'),
      $container->get('pager.manager'),
      $container->get('inline_block.usage', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $container->get('trash.manager', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * Lists the usage of a given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int|string $entity_id
   *   The entity ID.
   *
   * @return mixed[]
   *   The page build to be rendered.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function listUsagePage(string $entity_type, int|string $entity_id): array {
    $entries = $this->getDisplayableEntries($entity_type, $entity_id);
    if (empty($entries)) {
      return [
        '#markup' => $this->t(
          'There are no recorded usages for entity of type: @type with id: @id',
          ['@type' => $entity_type, '@id' => $entity_id]
        ),
      ];
    }

    $header = [
      $this->t('Entity'),
      $this->t('Type'),
      $this->t('Language'),
      $this->t('Field name'),
      $this->t('Used in'),
    ];

    $total = count($entries);
    $pager = $this->pagerManager->createPager($total, $this->itemsPerPage);
    $page = $pager->getCurrentPage();
    $page_entries = array_slice($entries, $page * $this->itemsPerPage, $this->itemsPerPage);
    $page_rows = $this->buildRows($page_entries);

    $build[] = [
      '#theme' => 'table',
      '#rows' => $page_rows,
      '#header' => $header,
    ];

    $build[] = [
      '#type' => 'pager',
      '#route_name' => '<current>',
    ];

    return $build;
  }

  /**
   * Retrieve all displayable usage entries for this target entity.
   *
   * This only queries the raw usage records and runs lightweight
   * existence/field-value checks; it never fully loads a source entity. That
   * way the pager total and entry ordering are correct (they account for
   * every filtering condition that would otherwise only be discoverable after
   * a full load), without the cost of loading every source entity just to
   * show one page of results.
   *
   * @param string $entity_type
   *   The type of the target entity.
   * @param int|string $entity_id
   *   The ID of the target entity.
   *
   * @return array<int, array{source_type: string, source_id: int|string, records: mixed[]}>
   *   An indexed array of usage entries that should be displayed as sources
   *   for this target entity.
   */
  protected function getDisplayableEntries(string $entity_type, int|string $entity_id): array {
    if (isset($this->displayableEntries)) {
      return $this->displayableEntries;
      // @todo Cache this based on the target entity, invalidating the cached
      // results every time records are added/removed to the same target entity.
    }

    $entries = [];

    // Tell the Trash module not to hide entities that are trashed.
    if (!is_null($this->trashManager)) {
      $prev_trash_context = $this->trashManager->getTrashContext();
      $this->trashManager->setTrashContext('ignore');
    }
    try {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
      if ($entity) {
        foreach ($this->entityUsage->listSources($entity) as $source_type => $ids) {
          foreach ($ids as $source_id => $records) {
            $entries[] = [
              'source_type' => $source_type,
              'source_id' => $source_id,
              'records' => $records,
            ];
          }
        }
        $entries = $this->filterDisplayableEntries($entries);
      }
    }
    finally {
      // Restore previous trash context if we changed it.
      if (isset($prev_trash_context)) {
        $this->trashManager->setTrashContext($prev_trash_context);
      }
    }

    $this->displayableEntries = $entries;
    return $this->displayableEntries;
  }

  /**
   * Filters out usage entries that would not produce a displayable row.
   *
   * This is the single place that decides whether a source entity should be
   * skipped (orphaned usage records, soft-deleted inline blocks). It runs as
   * batched existence/field-value queries instead of full entity loads, since
   * this may run over every usage record for the target entity (not just the
   * current page), and it is what determines the pager total. ::buildRows()
   * trusts its output and does not repeat any of these checks; any new
   * condition for skipping a source entity belongs here.
   *
   * @param array<int, array{source_type: string, source_id: int|string, records: mixed[]}> $entries
   *   The candidate usage entries.
   *
   * @return array<int, array{source_type: string, source_id: int|string, records: mixed[]}>
   *   The entries whose source entity exists and would be displayed.
   */
  protected function filterDisplayableEntries(array $entries): array {
    if (empty($entries)) {
      return $entries;
    }

    $ids_by_type = [];
    foreach ($entries as $entry) {
      $ids_by_type[$entry['source_type']][] = $entry['source_id'];
    }

    // Determine which candidate IDs actually exist, without loading full
    // entities. This drops orphaned usage records, i.e. records whose source
    // entity has since been deleted but not yet cleaned up here.
    $existing_ids = [];
    foreach ($ids_by_type as $source_type => $ids) {
      $storage = $this->entityTypeManager->getStorage($source_type);
      $result = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($storage->getEntityType()->getKey('id'), $ids, 'IN')
        ->execute();
      // For revisionable entity types this is keyed by revision ID, not
      // entity ID (the entity ID is only guaranteed to be the value). Flip it
      // so entity ID membership can be checked with isset() regardless of
      // whether the entity type is revisionable.
      $existing_ids[$source_type] = array_flip($result);
    }

    // Soft-deleted inline blocks are always block_content entities. Layout
    // Builder's cron hook will delete them eventually, but until then we
    // don't want them showing as a source unless they still have an
    // associated host entity. Determine this with field-value queries and a
    // lookup against the (small, dedicated) inline block usage table, rather
    // than loading each block_content entity.
    $hidden_inline_block_ids = [];
    if ($this->inlineBlockUsage && isset($existing_ids['block_content'])) {
      $block_storage = $this->entityTypeManager->getStorage('block_content');
      $non_reusable_ids = $block_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($block_storage->getEntityType()->getKey('id'), array_keys($existing_ids['block_content']), 'IN')
        ->condition('reusable', 0)
        ->execute();
      foreach ($non_reusable_ids as $id) {
        $blockUsageData = $this->inlineBlockUsage->getUsage((int) $id);
        if (!$blockUsageData || is_null($blockUsageData->layout_entity_id)) {
          $hidden_inline_block_ids[$id] = TRUE;
        }
      }
    }

    return array_values(array_filter($entries, function (array $entry) use ($existing_ids, $hidden_inline_block_ids): bool {
      if (!isset($existing_ids[$entry['source_type']][$entry['source_id']])) {
        return FALSE;
      }
      if ($entry['source_type'] === 'block_content' && isset($hidden_inline_block_ids[$entry['source_id']])) {
        return FALSE;
      }
      return TRUE;
    }));
  }

  /**
   * Build table rows for a set of usage entries.
   *
   * Source entities are batch-loaded per entity type, so this should only be
   * called with the slice of entries actually being displayed (i.e. the
   * current page), not the full set of usage entries for a target entity.
   *
   * @param array<int, array{source_type: string, source_id: int|string, records: mixed[]}> $entries
   *   The usage entries to build rows for. These are expected to have already
   *   been through ::filterDisplayableEntries(), which is where any filtering
   *   that affects the row count/pager total belongs. The only check repeated
   *   here is that the source entity actually loaded, as a safety net.
   *
   * @return mixed[]
   *   An indexed array of rows that should be displayed as sources for this
   *   target entity.
   */
  protected function buildRows(array $entries): array {
    $rows = [];
    if (empty($entries)) {
      return $rows;
    }

    // Tell the Trash module not to hide entities that are trashed.
    if (!is_null($this->trashManager)) {
      $prev_trash_context = $this->trashManager->getTrashContext();
      $this->trashManager->setTrashContext('ignore');
    }
    try {
      // Batch load all the source entities per type, instead of loading them
      // one at a time.
      $ids_by_type = [];
      foreach ($entries as $entry) {
        $ids_by_type[$entry['source_type']][] = $entry['source_id'];
      }
      $source_entities = [];
      foreach ($ids_by_type as $source_type => $ids) {
        $source_entities[$source_type] = $this->entityTypeManager->getStorage($source_type)->loadMultiple($ids);
      }

      $entity_types = $this->entityTypeManager->getDefinitions();
      $languages = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);

      foreach ($entries as $entry) {
        $source_type = $entry['source_type'];
        $source_id = $entry['source_id'];
        $records = $entry['records'];

        // We will show a single row per source entity. If the target is not
        // referenced on its default revision on the default language, we will
        // just show indicate that in a specific column.
        $source_entity = $source_entities[$source_type][$source_id] ?? NULL;
        if (!$source_entity) {
          // If for some reason this record is broken, just skip it.
          continue;
        }

        // The effective published status of the source (or its host, for
        // inline blocks).
        $source_entity_status = $this->getSourceEntityStatus($source_entity);

        $field_definitions = $this->entityFieldManager->getFieldDefinitions($source_type, $source_entity->bundle());
        $default_langcode = $source_entity->language()->getId();
        $used_in = [];
        $revisions = [];

        if ($source_entity instanceof RevisionableInterface) {
          $default_revision_id = (int) $source_entity->getRevisionId();
          // Track the distinct vids seen per (group, langcode) so multiple
          // records that share a vid (e.g. a host with both a direct
          // entity_reference field and an entity_reference_revisions field
          // pointing at the same target) are not counted as separate
          // revisions.
          $pending_vids_by_lang = [];
          $old_vids = [];
          foreach ($records as $record) {
            [
              'source_vid' => $source_vid,
              'source_langcode' => $source_langcode,
            ] = $record;

            // Track which languages are used in pending, default and old
            // revisions.
            $revision_group = (int) $source_vid <=> $default_revision_id;
            // If the default revision is unpublished, it is really a draft.
            if ($revision_group === static::REVISION_DEFAULT && $source_entity_status === SourceEntityStatus::Unpublished) {
              $revision_group = static::REVISION_PENDING;
            }
            // If a different pending vid for this language has already been
            // recorded, demote this record to OLD. An editor only sees the
            // latest draft per translation via the edit UI. Records that
            // share the same vid (different field/method) are ignored here so
            // they do not get spuriously demoted.
            if ($revision_group === static::REVISION_PENDING
              && isset($pending_vids_by_lang[$source_langcode])
              && !isset($pending_vids_by_lang[$source_langcode][$source_vid])
            ) {
              $revision_group = static::REVISION_OLD;
            }
            if ($revision_group === static::REVISION_PENDING) {
              $pending_vids_by_lang[$source_langcode][$source_vid] = TRUE;
            }

            if ($revision_group === static::REVISION_OLD) {
              // Record the old vids so we can show the number of distinct
              // revisions.
              $old_vids[$source_vid] = TRUE;
            }
            $revisions[$revision_group][$source_langcode] = TRUE;
          }

          $has_default = !empty($revisions[static::REVISION_DEFAULT]);
          $revision_group_labels = [
            static::REVISION_PENDING => $this->t('Draft revision'),
            static::REVISION_OLD => $this->formatPlural(count($old_vids), '@count old revision', '@count old revisions'),
          ];
          if ($has_default) {
            $used_in[] = $this->summarizeRevisionGroup($default_langcode, $source_entity_status->label(), $revisions[static::REVISION_DEFAULT]);
          }
          foreach ($revision_group_labels as $index => $label) {
            if (!empty($revisions[$index])) {
              $used_in[] = $this->summarizeRevisionGroup($default_langcode, $label, $revisions[$index]);
            }
          }

          if (count($used_in) > 1) {
            $used_in = [
              '#theme' => 'item_list',
              '#items' => $used_in,
              '#list_type' => 'ul',
            ];
          }
        }
        else {
          $used_in[] = $source_entity_status->label();
        }
        // @todo List all the fields that use the target entity.
        $field_name = $records[0]['field_name'];
        $field_label = isset($field_definitions[$field_name])
          ? $field_definitions[$field_name]->getLabel()
          : $this->t('Unknown');

        $type = $entity_types[$source_type]->getLabel();
        if ($source_bundle_key = $source_entity->getEntityType()->getKey('bundle')) {
          $bundle_field = $source_entity->{$source_bundle_key};
          if ($bundle_field->getFieldDefinition()->getType() === 'entity_reference') {
            $bundle_label = $bundle_field->entity->label();
          }
          else {
            $bundle_label = $bundle_field->getString();
          }
          $type .= ': ' . $bundle_label;
        }

        $rows[] = [
          $this->getSourceEntityLink($source_entity),
          $type,
          $languages[$default_langcode]->getName(),
          $field_label,
          ['data' => $used_in],
        ];
      }
    }
    finally {
      // Restore previous trash context if we changed it.
      if (isset($prev_trash_context)) {
        $this->trashManager->setTrashContext($prev_trash_context);
      }
    }

    return $rows;
  }

  /**
   * Returns a render array indicating a revision "type" and languages.
   *
   * For example it might return "Draft revisions (ES, NO)".
   *
   * @param string $default_langcode
   *   The default language code for the referencing entity.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $revision_label
   *   The translated revision-group label, eg 'Old revisions' or the host's
   *   publish-status label.
   * @param bool[] $languages
   *   An array keyed by language codes that reference the entity in the given
   *   type.
   *
   * @return mixed[]
   *   A render array summarizing the information passed in.
   */
  protected function summarizeRevisionGroup(string $default_langcode, TranslatableMarkup $revision_label, array $languages): array {
    $language_objects = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    if (count($languages) === 1 && !empty($languages[$default_langcode])) {
      // If there's only one relevant revision and it's the entity's default
      // language then just show the label.
      return ['#plain_text' => $revision_label];
    }
    else {
      // Otherwise show the languages enumerated, ensuring the default language
      // comes first if present.
      if (!empty($languages[$default_langcode])) {
        $languages = [$default_langcode => TRUE] + $languages;
      }
      // Ignore not installed languages.
      $languages = array_intersect_key($languages, $language_objects);
      return [
        '#type' => 'inline_template',
        '#template' => '{{ label }} ({% for language in languages %}{{ language }}{{ loop.last ? "" : ", " }}{% endfor %})',
        '#context' => [
          'label' => $revision_label,
          'languages' => array_map(fn ($code) => [
            '#type' => 'inline_template',
            '#template' => '<abbr title="{{ name|e("html_attr") }}">{{ code }}</abbr>',
            '#context' => [
              'code' => mb_strtoupper($code),
              'name' => $language_objects[$code]->getName(),
            ],
          ], array_keys($languages)),
        ],
      ];
    }
  }

  /**
   * Title page callback.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int|string $entity_id
   *   The entity id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title to be used on this page.
   */
  public function getTitle(string $entity_type, int|string $entity_id): TranslatableMarkup {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if ($entity) {
      return $this->t('Entity usage information for %entity_label', ['%entity_label' => $entity->label()]);
    }
    return $this->t('Entity Usage List');
  }

  /**
   * Retrieve the source entity's status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   *
   * @return \Drupal\entity_usage\SourceEntityStatus
   *   The source entity's status.
   */
  protected function getSourceEntityStatus(EntityInterface $source_entity): SourceEntityStatus {
    // Use the status from the host entity for inline content blocks.
    if ($source_entity instanceof BlockContentInterface && !$source_entity->isReusable()) {
      $parent = $this->getContentBlockParentEntity($source_entity);
      if (!empty($parent)) {
        return $this->getSourceEntityStatus($parent);
      }
    }

    if ($source_entity instanceof EntityPublishedInterface) {
      return $source_entity->isPublished() ? SourceEntityStatus::Published : SourceEntityStatus::Unpublished;
    }
    return SourceEntityStatus::Current;
  }

  /**
   * Retrieve a link to the source entity.
   *
   * Note that some entities are special-cased, since they don't have canonical
   * template and aren't expected to be re-usable. For example, if the entity
   * passed in is a block content entity, the link we produce will point to this
   * entity's parent (host) entity instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   *
   * @return \Drupal\Core\Link|string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   A link to the entity, or its non-linked label, in case it was impossible
   *   to correctly build a link.
   */
  protected function getSourceEntityLink(EntityInterface $source_entity): Link|string|TranslatableMarkup {
    // Treat block_content entities in a special manner. Block content
    // relationships are stored as serialized data on the host entity. This
    // makes it difficult to query parent data. Instead we look up relationship
    // data which may exist in entity_usage tables. This requires site builders
    // to set up entity usage on host-entity-type -> block_content manually.
    // @todo this could be made more generic to support other entity types with
    // difficult to handle parent -> child relationships.
    if ($source_entity instanceof BlockContentInterface && !$source_entity->isReusable()) {
      $parent = $this->getContentBlockParentEntity($source_entity);
      if ($parent) {
        return $this->getSourceEntityLink($parent);
      }
    }

    $entity_in_trash = !is_null($this->trashManager) && Trash::entityIsDeleted($source_entity);

    $entity_label = $source_entity->access('view label') ? $source_entity->label() : $this->t('- Restricted access -');
    if ($entity_in_trash) {
      $entity_label .= ' ' . $this->t('(in trash)');
    }

    $rel = NULL;
    if ($source_entity->hasLinkTemplate('revision')) {
      $rel = 'revision';
    }
    elseif ($source_entity->hasLinkTemplate('canonical')) {
      $rel = 'canonical';
    }

    // Block content likely used in Layout Builder inline or reusable blocks.
    if ($source_entity instanceof BlockContentInterface) {
      $rel = NULL;
    }

    if ($rel) {
      // Prevent 404s by exposing the text unlinked if the user has no access
      // to view the entity.
      $options = [];
      if ($entity_in_trash) {
        // Trashed entities need a query string parameter to allow viewing.
        $options['query'] = ['in_trash' => TRUE];
      }
      return $source_entity->access('view') ? $source_entity->toLink($entity_label, $rel, $options) : $entity_label;
    }

    // As a fallback just return a non-linked label.
    return $entity_label;
  }

  /**
   * Figure out the "parent" entity of a content block.
   *
   * @param \Drupal\block_content\BlockContentInterface $block_content
   *   The block entity we are interested in.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity that has a tracked relationship pointing to this block.
   */
  private function getContentBlockParentEntity(BlockContentInterface $block_content): ?EntityInterface {
    $sources = $this->entityUsage->listSources($block_content, FALSE);
    $source = reset($sources);
    if (!empty($source['source_type']) && !empty($source['source_id'])) {
      return $this->entityTypeManager()->getStorage($source['source_type'])->load($source['source_id']);
    }
    return NULL;
  }

  /**
   * Checks access based on whether the user can view the current entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int|string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(string $entity_type, int|string $entity_id): AccessResultInterface {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity || !$entity->access('view')) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
