<?php

namespace Drupal\eca_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\eca\Processor;

/**
 * Implements render hooks for the ECA UI module.
 */
class RenderHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new TemplateHooks object.
   */
  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_page_bottom().
   */
  #[Hook('page_bottom', order: Order::Last)]
  public function pageBottom(array &$page_bottom): void {
    if (!($this->state->get('_eca_internal_debug_mode', FALSE) ?? FALSE)) {
      return;
    }
    $items = [];
    foreach (Processor::getAppliedEvents() as $processDebugger) {
      if (!$processDebugger->isStarted()) {
        continue;
      }
      $hash = $processDebugger->getHistoryHash();
      $items[] = [
        '#type' => 'link',
        '#title' => $processDebugger->getEventLabel(),
        '#url' => Url::fromRoute('entity.eca.edit_form', ['eca' => $processDebugger->getEcaId()], [
          'query' => [
            'select' => $processDebugger->getEventId(),
            'hash' => $hash,
          ],
        ]),
        '#attributes' => [
          'data-modeler-eca-id' => $processDebugger->getEcaId(),
          'data-modeler-eca-event-id' => $processDebugger->getEventId(),
          'data-modeler-eca-hash' => $hash,
        ],
      ];
    }
    if ($items) {
      $page_bottom['eca_ui_debug'] = [
        '#type' => 'container',
        'title' => [
          '#type' => 'markup',
          '#markup' => $this->t('ECA events applied on this page:'),
          '#prefix' => '<h2>',
          '#suffix' => '</h2>',
        ],
        'events' => [
          '#theme' => 'item_list',
          '#items' => $items,
          '#list_type' => 'ul',
          '#attributes' => [
            'data-modeler-api-edit-links' => TRUE,
          ],
        ],
        '#attributes' => [
          'id' => ['eca-ui-debug-applied-events'],
        ],
        '#attached' => ['library' => ['eca_ui/debug']],
      ];
    }
  }

}
