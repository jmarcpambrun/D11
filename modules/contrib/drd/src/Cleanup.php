<?php

namespace Drupal\drd;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drd\Entity\Core;
use Drupal\drd\Entity\Major;
use Drupal\drd\Entity\Project;
use Drupal\drd\Entity\Release;

/**
 * Provides services to cleanup DRD entities.
 *
 * @package Drupal\drd
 */
class Cleanup {

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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Cleanup constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->database = $database;
  }

  /**
   * Cleanup process for all project, major and release entities.
   */
  public function execute(): void {
    $config = $this->configFactory->get('drd.general');
    if ($config->get('cleanup.releases')) {
      $this->cleanupReleases();
    }
    if ($config->get('cleanup.majors')) {
      $this->cleanupMajors();
    }
    if ($config->get('cleanup.projects')) {
      $this->cleanupProjects();
    }
  }

  /**
   * Cleanup process for all release entities.
   */
  public function cleanupReleases(): void {
    $query = $this->database->select('drd_release', 'r');
    $query->leftJoin('drd_domain__releases', 'd', 'r.id=d.releases_target_id');
    $query->join('drd_major', 'm', 'r.major=m.id');
    $query->fields('r', ['id'])
      ->isNull('d.entity_id')
      ->isNull('m.parentproject')
      ->condition('m.recommended', 'r.id', '<>');
    $results = $query->execute();
    if ($results !== NULL) {
      foreach ($results->fetchCol() as $id) {
        if ($release = Release::load($id)) {
          try {
            $release->delete();
          }
          catch (EntityStorageException) {
            // @todo Log this exception.
          }
        }
      }
    }
  }

  /**
   * Cleanup process for all major entities.
   */
  public function cleanupMajors(): void {
    $query = $this->database->select('drd_major', 'm');
    $query->leftJoin('drd_release', 'r', 'm.id=r.major');
    $query->fields('m', ['id'])
      ->isNull('r.major')
      ->isNull('m.parentproject');
    $results = $query->execute();
    if ($results !== NULL) {
      foreach ($results->fetchCol() as $id) {
        if ($major = Major::load($id)) {
          try {
            $major->delete();
          }
          catch (EntityStorageException) {
            // @todo Log this exception.
          }
        }
      }
    }
  }

  /**
   * Cleanup process for all project entities.
   */
  public function cleanupProjects(): void {
    $query = $this->database->select('drd_project', 'p');
    $query->leftJoin('drd_major', 'm', 'p.id=m.project');
    $query->fields('p', ['id'])
      ->isNull('m.project');
    $results = $query->execute();
    if ($results !== NULL) {
      foreach ($results->fetchCol() as $id) {
        if ($project = Project::load($id)) {
          try {
            $project->delete();
          }
          catch (EntityStorageException) {
            // @todo Log this exception.
          }
        }
      }
    }
  }

  /**
   * Reset for all project, major and release entities.
   */
  public function resetAll(): void {
    // Remember core versions.
    $cores = [];
    foreach (Core::loadMultiple() as $core) {
      /** @var \Drupal\drd\Entity\CoreInterface $core */
      $cores[] = [
        'core' => $core,
        'version' => ($release = $core->getDrupalRelease()) ? $release->getVersion() : '',
      ];
    }

    // Delete all entities.
    foreach (['release', 'major', 'project'] as $item) {
      $entity_type_id = 'drd_' . $item;
      try {
        if ($entity_type = $this->entityTypeManager->getDefinition($entity_type_id)) {
          $storage = $this->entityTypeManager->getStorage($entity_type_id);

          while ($entity_ids = $storage->getQuery()
            ->sort($entity_type->getKey('id'))
            ->range(0, 10)
            ->accessCheck(FALSE)
            ->execute()) {
            /** @var int[] $entity_ids */
            array_walk($entity_ids, static function (&$item) {
              $item = (int) $item;
            });
            if ($entities = $storage->loadMultiple($entity_ids)) {
              $storage->delete($entities);
            }
          }
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException | EntityStorageException) {
        // @todo Log this exception.
      }
    }

    // Re-create Drupal core versions.
    foreach ($cores as $item) {
      /** @var \Drupal\drd\Entity\CoreInterface $core */
      $core = $item['core'];
      $release = Release::findOrCreate('core', 'drupal', $item['version']);
      try {
        $core
          ->setDrupalRelease($release)
          ->save();
      }
      catch (EntityStorageException) {
        // @todo Log this exception.
      }
    }
  }

}
