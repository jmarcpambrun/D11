<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_usage\EntityUpdateManagerInterface;
use Drupal\entity_usage\PreSaveUrlRecorder;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Entity hook implementations for entity_usage.
 *
 * @todo Replace with direct calls to service if
 *   https://www.drupal.org/node/3481903 lands.
 */
class EntityUsageEntityHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly PreSaveUrlRecorder $preSaveUrlRecorder,
    private readonly EntityUpdateManagerInterface $entityUsageUpdateManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (!$entity->isNew() && ($entity->hasLinkTemplate('canonical') || $entity->hasLinkTemplate('edit-form'))) {
      $config = $this->configFactory->get('entity_usage.settings');
      $enabled_target_entity_types = $config->get('track_enabled_target_entity_types');
      // Every entity type is tracked if not set.
      if (!is_array($enabled_target_entity_types) || in_array($entity->getEntityTypeId(), $enabled_target_entity_types, TRUE)) {
        $this->preSaveUrlRecorder->recordEntity($entity);
      }
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->entityUsageUpdateManager->trackUpdateOnCreation($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->entityUsageUpdateManager->trackUpdateOnEdition($entity);
    // The previous URL is no longer needed so remove it to save memory in case
    // this is a part of a long-running process updating a large number of
    // entities.
    $this->preSaveUrlRecorder->removeEntity($entity);
  }

  /**
   * Implements hook_entity_predelete().
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {
    $this->entityUsageUpdateManager->trackUpdateOnDeletion($entity);
  }

  /**
   * Implements hook_entity_translation_delete().
   */
  #[Hook('entity_translation_delete')]
  public function entityTranslationDelete(EntityInterface $translation): void {
    $this->entityUsageUpdateManager->trackUpdateOnDeletion($translation, 'translation');
  }

  /**
   * Implements hook_entity_revision_delete().
   */
  #[Hook('entity_revision_delete')]
  public function entityRevisionDelete(EntityInterface $entity): void {
    $this->entityUsageUpdateManager->trackUpdateOnDeletion($entity, 'revision');
  }

}
