<?php

namespace Drupal\drd;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Easy access to the DRD widgets.
 */
class Widgets {

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected BlockManagerInterface $blockManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Widgets constructor.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(BlockManagerInterface $blockManager, AccountInterface $currentUser) {
    $this->blockManager = $blockManager;
    $this->currentUser = $currentUser;
  }

  /**
   * Get a list of all available widgets for the dashboard.
   *
   * @param bool $render
   *   Whether to render the widget's content.
   *
   * @return \Drupal\drd\Plugin\Block\Base[]
   *   List of widgets.
   */
  public function findWidgets($render): array {
    $widgets = [];
    foreach ($this->blockManager->getDefinitions() as $definition) {
      if (isset($definition['tags']) && in_array('drd_widget', $definition['tags'], TRUE)) {
        if (!isset($definition['weight'])) {
          $definition['weight'] = 0;
        }
        if ($render) {
          /** @var \Drupal\drd\Plugin\Block\Base $block */
          try {
            $block = $this->blockManager->createInstance($definition['id'], []);
            if ($block->access($this->currentUser)) {
              $widgets[$definition['weight']] = [
                '#type' => 'container',
                '#attributes' => [
                  'class' => ['drd-widget'],
                ],
                '#weight' => $definition['weight'],
              ] + $block->build();
            }
          }
          catch (PluginException $e) {
          }
        }
        else {
          $widgets[$definition['weight']] = $definition;
        }
      }
    }
    ksort($widgets);
    return $widgets;
  }

}
