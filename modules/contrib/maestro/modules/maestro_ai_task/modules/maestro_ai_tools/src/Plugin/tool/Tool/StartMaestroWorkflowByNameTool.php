<?php

declare(strict_types=1);

namespace Drupal\maestro_ai_tools\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\MapInputDefinition;

/**
 * Starts any public Maestro workflow by machine name.
 *
 * StartMaestroWorkflowTool exposes one distinct, individually-typed tool per
 * template via a deriver, better parameter typing, but an LLM can only call
 * whichever of those were curated into its attached tool set at config
 * time. This tool is the deliberate complement: a single, always-available
 * tool that starts a workflow by name, so a workflow discovered via
 * list_maestro_workflows (including one added after any curation happened)
 * can still be started even if its own specific "Start Maestro Workflow: ..."
 * tool isn't currently attached. The trade-off is weaker typing on
 * "variables", a generic map rather than named, described parameters per
 * template, since the target template isn't known until the tool is
 * called.
 */
#[Tool(
  id: 'start_maestro_workflow_by_name',
  label: new TranslatableMarkup('Start Maestro Workflow (by machine name)'),
  description: new TranslatableMarkup('Starts a public Maestro workflow given its machine name. Prefer a specific "Start Maestro Workflow: ..." tool if one is available for the workflow you want its parameters are individually named and described. Use this tool instead when no such specific tool is available, for example for a workflow you only just learned about via list_maestro_workflows.'),
  operation: ToolOperation::Trigger,
  input_definitions: [
    'template' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Template machine name'),
      description: new TranslatableMarkup('The machine name of the public Maestro workflow template to start, as returned by list_maestro_workflows.'),
      required: TRUE,
    ),
    'variables' => new MapInputDefinition(
      label: new TranslatableMarkup('Process variables'),
      description: new TranslatableMarkup('Optional map of process variable names to string values for the target template. Use list_maestro_workflows to see which variable names a given template accepts.'),
      required: FALSE,
    ),
  ],
  output_definitions: [
    'process_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Process ID'),
      description: new TranslatableMarkup('The ID of the newly started Maestro process.'),
    ),
  ],
)]
final class StartMaestroWorkflowByNameTool extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $template_name = $values['template'];
    $variables = $values['variables'] ?? [];

    $templates = MaestroEngine::getTemplates();
    if (!array_key_exists($template_name, $templates)) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Template "@template" does not exist. Use list_maestro_workflows to see available templates.', ['@template' => $template_name]),
      );
    }
    if (!empty($templates[$template_name]->private)) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Template "@template" is marked private and cannot be started via this tool.', ['@template' => $template_name]),
      );
    }
    if (empty($templates[$template_name]->validated)) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Template "@template" has not been validated. It must be validated in the Maestro modeller before it can be started.', ['@template' => $template_name]),
      );
    }

    $maestro = new MaestroEngine();
    $process_id = $maestro->newProcess($template_name);
    if ($process_id === FALSE) {
      return ExecutableResult::failure(
        new TranslatableMarkup('Failed to start workflow "@template" for an unknown reason.', ['@template' => $template_name]),
      );
    }

    foreach ($variables as $name => $value) {
      if ($value === NULL || $value === '' || !is_scalar($value)) {
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
   * Gates on the same per-template permission StartMaestroWorkflowTool
   * uses (MaestroEnginePermissions::permissions(): 'start template
   * <machine_name>'), based on the resolved 'template' value, a single
   * generic tool doesn't lose per-template permission enforcement.
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $template_name = $values['template'] ?? NULL;
    if (!$template_name) {
      $result = AccessResult::forbidden('No template specified.');
      return $return_as_object ? $result : $result->isAllowed();
    }
    $result = AccessResult::allowedIfHasPermission($account, 'start template ' . $template_name);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
