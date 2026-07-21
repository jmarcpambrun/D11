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

/**
 * Lists Maestro workflow templates the current user is permitted to start.
 *
 * StartMaestroWorkflowTool exposes one tool per template, so an LLM only
 * ever sees whichever of those were curated into its attached tool set
 * there is no way for it to discover, or immediately call, a workflow that
 * was added after that curation happened. This tool doesn't remove that
 * limitation (a newly created workflow's own starter tool still has to be
 * added to whatever tool set uses it), but it does give a way to see what
 * currently exists and what each one's starter tool expects, rather than
 * that being invisible entirely. Results are filtered to templates the
 * acting user can actually start, so this can't be used to enumerate
 * workflows the caller has no rights to trigger.
 */
#[Tool(
  id: 'list_maestro_workflows',
  label: new TranslatableMarkup('List Maestro Workflows'),
  description: new TranslatableMarkup('Lists Maestro workflow templates the current user is permitted to start, their machine names, and their process variable names. Use this to discover which "Start Maestro Workflow: ..." tool to call for a given workflow and what variables it accepts.'),
  operation: ToolOperation::Explain,
  input_definitions: [],
  output_definitions: [
    'workflows' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Workflows'),
      description: new TranslatableMarkup('A description of each public Maestro workflow template and its process variables.'),
    ),
  ],
)]
final class ListMaestroWorkflowsTool extends ToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $blocks = [];

    foreach (MaestroEngine::getTemplates() as $machine_name => $template) {
      if (!empty($template->private)) {
        continue;
      }
      if (!$this->currentUser->hasPermission('start template ' . $machine_name)) {
        continue;
      }

      $variable_names = array_keys(MaestroEngine::getTemplateVariables($machine_name));

      $blocks[] = implode("\n", [
        "Machine name: {$machine_name}",
        'Label: ' . (string) $template->label(),
        'Description: ' . ($template->getDescription() ?: '(none)'),
        'Process variables: ' . (empty($variable_names) ? '(none)' : implode(', ', $variable_names)),
      ]);
    }

    return ExecutableResult::success(
      new TranslatableMarkup('Found @count public Maestro workflow(s).', ['@count' => count($blocks)]),
      ['workflows' => empty($blocks) ? 'No public Maestro workflows found.' : implode("\n\n", $blocks)],
    );
  }

  /**
   * {@inheritdoc}
   *
   * The tool itself is always callable access is not gated here because
   * there's nothing to check yet (no specific template is named until
   * doExecute() runs). The per-template 'start template <name>' permission
   * is instead applied as a row filter in doExecute(), so the returned list
   * never names a workflow the caller couldn't also start.
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

}
