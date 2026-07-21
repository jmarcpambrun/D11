<?php

declare(strict_types=1);

namespace Drupal\maestro_ai_tools\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_ai_tools\Plugin\tool\Tool\Derivative\StartMaestroWorkflowToolDeriver;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;

/**
 * Starts a public Maestro workflow process.
 *
 * One derivative per public Maestro template (see
 * StartMaestroWorkflowToolDeriver), the derivative id is the template's own
 * machine name, and each process variable it declares becomes its own named
 * input, so an AI caller sees a distinct, self-describing tool per workflow
 * rather than a generic template-name-plus-JSON-blob interface.
 */
#[Tool(
  id: 'start_maestro_workflow',
  label: new TranslatableMarkup('Start Maestro Workflow'),
  description: new TranslatableMarkup('Starts a Maestro workflow process.'),
  operation: ToolOperation::Trigger,
  input_definitions: [],
  output_definitions: [
    'process_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Process ID'),
      description: new TranslatableMarkup('The ID of the newly started Maestro process.'),
    ),
  ],
  deriver: StartMaestroWorkflowToolDeriver::class,
)]
final class StartMaestroWorkflowTool extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $template_name = $this->getDerivativeId();

    $maestro = new MaestroEngine();
    $process_id = $maestro->newProcess($template_name);
    if ($process_id === FALSE) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Failed to start workflow "@template". Ensure the template has been validated in the Maestro modeller.', ['@template' => $template_name]),
      );
    }

    foreach ($values as $name => $value) {
      if ($value === NULL || $value === '') {
        continue;
      }
      MaestroEngine::setProcessVariable($name, (string) $value, $process_id);
    }

    return ExecutableResult::success(
      new TranslatableMarkup('Maestro workflow "@template" started. Process ID: @pid', [
        '@template' => $template_name,
        '@pid' => $process_id,
      ]),
      ['process_id' => (int) $process_id],
    );
  }

  /**
   * {@inheritdoc}
   *
   * Gates on Maestro's own dynamically-generated per-template permission
   * (MaestroEnginePermissions::permissions(): 'start template <machine_name>').
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'start template ' . $this->getDerivativeId());
    return $return_as_object ? $result : $result->isAllowed();
  }

}
