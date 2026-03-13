<?php

namespace Drupal\quiz\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\replicate\Events\AfterSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * QuizEventSubscriber event subscriber.
 */
class QuizEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new QuizEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'replicate__after_save' => ['afterSave'],
    ];
  }

  /**
   * Copy questions to a new quiz revision.
   *
   * @param \Drupal\replicate\Events\AfterSaveEvent $event
   *   Event from the replicate module.
   */
  public function afterSave(AfterSaveEvent $event) {
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() == 'quiz') {
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('quiz');
      /** @var \Drupal\quiz\Entity\Quiz $old_quiz */
      $old_quiz = $storage->loadRevision($entity->old_vid);
      $entity->copyFromRevision($old_quiz);
    }
  }

}
