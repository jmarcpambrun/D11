<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\Core\Update\UpdateHookRegistry;
use Drupal\Core\Update\UpdateRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Update' code.
 */
class Update extends Base {

  /**
   * The update hook registry.
   *
   * @var \Drupal\Core\Update\UpdateHookRegistry
   */
  protected UpdateHookRegistry $updateHookRegistry;

  /**
   * The update registry.
   *
   * @var \Drupal\Core\Update\UpdateRegistry
   */
  protected UpdateRegistry $postUpdateRegistry;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->updateHookRegistry = $container->get('update.update_hook_registry');
    $instance->postUpdateRegistry = $container->get('update.post_update_registry');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): array {
    require_once DRUPAL_ROOT . '/core/includes/install.inc';
    require_once DRUPAL_ROOT . '/core/includes/update.inc';
    drupal_load_updates();

    // Pending hook_update_N() implementations.
    $pending = update_get_update_list();

    // Pending hook_post_update_X() implementations.
    $post_updates = $this->postUpdateRegistry->getPendingUpdateInformation();

    $result = [];
    $start = [];
    if (count($pending) || count($post_updates)) {
      foreach (['update', 'post_update'] as $update_type) {
        $updates = $update_type === 'update' ? $pending : $post_updates;
        foreach ($updates as $module => $module_updates) {
          if (isset($module_updates['start'])) {
            $start[$module] = $module_updates['start'];
          }
        }
      }
      $result = $this->batch($start);
    }
    return $result;
  }

  /**
   * Callback to determine and execute all required operations.
   *
   * @param array $start
   *   A list of operations.
   *
   * @return array
   *   List of results.
   */
  private function batch(array $start): array {
    $result = [];
    $updates = update_resolve_dependencies($start);
    $dependency_map = [];
    foreach ($updates as $function => $update) {
      $dependency_map[$function] = !empty($update['reverse_paths']) ? array_keys($update['reverse_paths']) : [];
    }

    $operations = [];
    foreach ($updates as $update) {
      if ($update['allowed']) {
        // Set the installed version of each module so updates will start at the
        // correct place. (The updates are already sorted, so we can simply base
        // this on the first one we come across in the above foreach loop.)
        if (isset($start[$update['module']])) {
          $this->updateHookRegistry->setInstalledVersion($update['module'], $update['number'] - 1);
          unset($start[$update['module']]);
        }
        // Add this update function to the batch.
        $function = $update['module'] . '_update_' . $update['number'];
        $operations[] = [
          'update_do_one',
          [
            $update['module'],
            $update['number'],
            $dependency_map[$function],
          ],
        ];
        $this->messenger->addMessage('Updating ' . $update['module'] . ': version ' . $update['number']);
      }
    }

    // Apply post update hooks.
    $post_updates = $this->postUpdateRegistry->getPendingUpdateFunctions();
    if ($post_updates) {
      $operations[] = ['drupal_flush_all_caches', []];
      foreach ($post_updates as $function) {
        $operations[] = ['update_invoke_post_update', [$function]];
      }
    }

    $maintenanceModeOriginalState = $this->state->get('system.maintenance_mode');
    $this->state->set('system.maintenance_mode', TRUE);
    $context = [
      'sandbox'  => [],
      'results'  => [],
      'message'  => '',
    ];
    $this->batchProcess($operations, $context);
    if (!empty($context['results']['#abort'])) {
      $result['failed'] = TRUE;
    }
    $this->captureUpdateMessages($context['results']);
    $this->state->set('system.maintenance_mode', $maintenanceModeOriginalState);
    return $result;
  }

  /**
   * Main loop to run operations until they've finished.
   *
   * @param array $operations
   *   The operations.
   * @param array $context
   *   The context.
   */
  private function batchProcess(array $operations, array &$context): void {
    foreach ($operations as $operation) {
      $context['finished'] = FALSE;
      $context['sandbox']['#finished'] = TRUE;
      $operation[1][] = &$context;
      $finished = FALSE;
      while (!$finished) {
        call_user_func_array($operation[0], $operation[1]);
        // @phpstan-ignore-next-line
        $finished = $context['finished'] || $context['sandbox']['#finished'];
      }
    }
  }

  /**
   * Callback to finally capture all messages from all operations.
   *
   * @param array $results
   *   The context of the operations.
   */
  private function captureUpdateMessages(array $results): void {
    foreach ($results as $module => $updates) {
      if ($module !== '#abort') {
        foreach ($updates as $queries) {
          foreach ($queries as $query) {
            // If there is no message for this update, don't show anything.
            if (empty($query['query'])) {
              continue;
            }

            if ($query['success']) {
              $this->messenger->addMessage($query['query']);
            }
            else {
              $this->messenger->addMessage($query['query'], 'error');
            }
          }
        }
      }
    }
  }

}
