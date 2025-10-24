<?php

namespace Drupal\webform_entity_builder\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform_entity_builder\Event\EntityBuildEvent;
use Drupal\webform_entity_builder\Plugin\EntityBuilderManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ActivityBuildEventSubscriber.
 */
class EntityBuildEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * @var LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var EntityBuilderManager
   */
  protected $entityBuilderManager;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ActivityEventSubscriber object.
   *
   * @param LoggerChannelFactoryInterface $loggerFactory
   * @param EntityTypeManagerInterface $entityTypeManager
   * @param EntityBuilderManager $entityBuilderManager
   */
  public function __construct(LoggerChannelFactoryInterface $loggerFactory,
    EntityTypeManagerInterface $entityTypeManager,
    EntityBuilderManager $entityBuilderManager
  ) {
    $this->logger = $loggerFactory->get('entity_build_event_subscriber');
    $this->entityBuilderManager = $entityBuilderManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    return [
      EntityBuildEvent::NAME => ['onEntityBuild', 0],
    ];
  }

  /**
   * @param EntityBuildEvent $event
   */
  public function onEntityBuild(EntityBuildEvent $event) {
    // Make sure an entity type is defined.
    if ($entityType = $event->getEntityType()) {
      try {
        $entityBuilder = $this->entityBuilderManager->fetchBuilder($entityType);
      }
      catch (PluginException $e) {
        $this->logger->error($this->t('Unable to find entity builder for "%a", because: @x', ['%a' => $entityType, '@x' => $e->getMessage()]));
        $entityBuilder = NULL;
      }

      if ($entityBuilder) {
        $values = $entityBuilder->build($event->getData());

        $entityId = $event->getEntityId();
        $type = $event->getEntityTypeId();

        /** @var EntityInterface $entity */
        try {
          if ($entityId === 0) {
            // This is a new entity.
            if ($entity = $this->entityTypeManager->getStorage($type)->create($values)) {
              $entity->save();
            }
            else {
              $this->logger->warning($this->t('Failed to create entity of type %a.', ['%a' => $type]));
            }
          }
          else {
            // This is modifying an existing entity.
            if ($entity = $this->entityTypeManager->getStorage($type)->load($entityId)) {
              foreach ($values as $field => $value) {
                $entity->set($field, $value);
              }
              $entity->save();
            }
            else {
              $this->logger->warning($this->t('Failed to load entity %i of type %a.', ['%i' => $entityId, '%a' => $type]));
            }

          }

          // Do any finalisation.
          $entityBuilder->finalise($entity);
        } catch (InvalidPluginDefinitionException|PluginNotFoundException|EntityStorageException $e) {
          $this->logger->error($this->t('Unable to save new entity in build because: @x', ['@x' => $e->getMessage()]));
        }
      }
      else {
        $this->logger->warning($this->t('Mysterious lack of entity builder for "%a".', ['%a' => $entityType]));
      }
    }
    else {
      $this->logger->warning($this->t('No entity type provided in Entity Builder event.'));
    }
  }

}
