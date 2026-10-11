<?php

namespace Drupal\book\Plugin\views\argument_default;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Plugin\views\argument_default\Node;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default argument plugin to get the current node's top level book.
 */
#[ViewsArgumentDefault(
  id: 'top_level_book',
  title: new TranslatableMarkup('Top Level Book from current node'),
)]
class TopLevelBook extends Node {

  public function __construct(array $configuration, $plugin_id, array $plugin_definition, RouteMatchInterface $route_match, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    // Use the argument_default_node plugin to get the nid argument.
    $nid = parent::getArgument();
    if (!empty($nid)) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      $book = $node->getBook();
      if (isset($book['bid'])) {
        return $book['bid'];
      }
    }
    return NULL;
  }

}
