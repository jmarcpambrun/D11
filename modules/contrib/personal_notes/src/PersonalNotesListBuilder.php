<?php

namespace Drupal\personal_notes;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of personal_notes entities.
 *
 * @see \Drupal\personal_notes\Entity\PersonalNote
 */
class PersonalNotesListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new NodeListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_type, $storage);

    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')
        ->getStorage($entity_type->id()),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {

    $header = [
      'id' => $this->t('ID'),
      'title' => $this->t('Title'),
      'note' => $this->t('Note'),
      'user' => $this->t('User'),
      'owner' => $this->t('Authored By'),
      'created' => [
        'data' => $this->t('Created On'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'changed' => [
        'data' => $this->t('Last Updated'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\personal_notes\Entity\PersonalNote $entity */

    $row['id']['data'] = [
      '#type' => 'link',
      '#title' => $entity->id(),
      '#url' => $entity->toUrl(),
    ];
    $row['title']['data'] = [
      '#type' => 'link',
      '#title' => $entity->getTitle(),
      '#url' => $entity->toUrl(),
    ];
    $row['note'] = $entity->getNote();
    if ($entity->getUser()) {
      $row['user']['data'] = [
        '#type' => 'link',
        '#title' => $entity->getUser()->getAccountName(),
        '#url' => $entity->getUser()->toUrl(),
      ];
    }
    else {
      $row['user'] = '';
    }
    $row['owner'] = $entity->getOwner()->getAccountName();
    $row['created'] = $this->dateFormatter->format($entity->getCreatedTime(), 'short');
    $row['changed'] = $this->dateFormatter->format($entity->getChangedTime(), 'short');
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->t("Personal Notes");
  }

}
