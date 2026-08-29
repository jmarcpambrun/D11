<?php

namespace Drupal\group\Plugin\views\argument;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler to accept a group ID.
 */
#[ViewsArgument('group_id')]
class GroupId extends NumericArgument {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Override the behavior of title(). Get the title of the group.
   */
  public function titleQuery() {
    $titles = [];

    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple($this->value);
    foreach ($groups as $group) {
      $titles[] = $group->label();
    }

    return $titles;
  }

}
