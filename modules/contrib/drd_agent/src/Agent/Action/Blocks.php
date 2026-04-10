<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\block\BlockListBuilder;

/**
 * Provides a 'Blocks' code.
 */
class Blocks extends Base {

  /**
   * Collect all available blocks and return them as a list.
   *
   * @return array
   *   List of block indexed by provider and ID, showing their label as value.
   */
  private function listBlocks(): array {
    $block_list = [];

    try {
      $blb = BlockListBuilder::createInstance($this->container, $this->entityTypeManager->getDefinition('block'));
      /** @var \Drupal\block\BlockInterface[] $blocks */
      $blocks = $blb->load();
      foreach ($blocks as $id => $block) {
        $definition = $block->getPlugin()->getPluginDefinition();
        $block_list[$definition['provider']][$id] = $block->label();
      }
    }
    catch (PluginNotFoundException $e) {
      // Ignore.
    }

    return $block_list;
  }

  /**
   * Load and return the rendered block.
   *
   * @param string $delta
   *   ID of the block from the given provider.
   *
   * @return \Drupal\Component\Render\MarkupInterface|array
   *   Rendered result of the block or an empty array.
   */
  private function renderBlock(string $delta): MarkupInterface|array {
    try {
      $blb = BlockListBuilder::createInstance($this->container, $this->entityTypeManager->getDefinition('block'));
    }
    catch (PluginNotFoundException $e) {
      return [];
    }
    /** @var \Drupal\block\BlockInterface[] $blocks */
    $blocks = $blb->load();
    if (isset($blocks[$delta])) {
      $block = $blocks[$delta];
      $build = $block->getPlugin()->build();
      return $this->container->get('renderer')->renderInIsolation($build);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): array {
    if (!$this->moduleHandler->moduleExists('block')) {
      return [];
    }
    $args = $this->getArguments();
    if (!empty($args['module']) && !empty($args['delta'])) {
      return [
        'data' => $this->renderBlock($args['delta']),
      ];
    }
    return $this->listBlocks();
  }

}
