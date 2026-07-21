<?php

declare(strict_types=1);

namespace Drupal\maestro_ai_tools\Plugin\tool\Tool\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Derives one Tool API tool per public Maestro workflow template.
 *
 * Private templates are excluded, matching the previous AiFunctionCall
 * implementation. Each derivative's id is the template's own machine name
 * (Drupal's standard base_id:derivative_id convention), so
 * StartMaestroWorkflowTool can resolve back to the target template via
 * $this->getDerivativeId() with no custom definition properties needed.
 *
 * Each of the template's own process variables becomes its own named,
 * described input Maestro process variables have no type metadata of
 * their own, so all are exposed as plain strings.
 */
final class StartMaestroWorkflowToolDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    foreach (MaestroEngine::getTemplates() as $machine_name => $template) {
      if (!empty($template->private)) {
        continue;
      }

      $input_definitions = [];
      foreach (MaestroEngine::getTemplateVariables($machine_name) as $variable_name => $variable) {
        $input_definitions[$variable_name] = new InputDefinition(
          data_type: $this->resolveDataType($variable),
          label: new TranslatableMarkup('@name', ['@name' => $variable_name]),
          description: new TranslatableMarkup('Process variable "@name" on the "@template" workflow.', [
            '@name' => $variable_name,
            '@template' => $machine_name,
          ]),
          required: FALSE,
        );
      }

      // $base_plugin_definition is a ToolDefinition object (tool module's
      // own attribute system)
      $definition = clone $base_plugin_definition;
      $definition->setLabel(new TranslatableMarkup('Start Maestro Workflow: @label', ['@label' => (string) $template->label()]));
      $definition->setDescription(new TranslatableMarkup('Starts the "@label" Maestro workflow.@description', [
        '@label' => (string) $template->label(),
        '@description' => $template->getDescription() ? ' ' . $template->getDescription() : '',
      ]));
      foreach ($input_definitions as $variable_name => $input_definition) {
        $definition->addInputDefinition($variable_name, $input_definition);
      }
      $this->derivatives[$machine_name] = $definition;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Resolves the Tool API data type for a single process variable.
   *
   * Maestro's variable schema (variable_id, variable_value) carries no type
   * of its own today, so this always falls back to 'string'. It reads an
   * optional 'variable_type' key defensively so that if Maestro core
   * starts declaring one, this single method is the only place that needs
   * to change not the deriver's calling code or anything downstream of it.
   *
   * @param array $variable
   *   The raw variable definition from MaestroEngine::getTemplateVariables().
   *
   * @return string
   *   A Tool API input data type, e.g. 'string', 'integer', 'boolean'.
   */
  protected function resolveDataType(array $variable): string {
    return match ($variable['variable_type'] ?? NULL) {
      'integer' => 'integer',
      'boolean' => 'boolean',
      default => 'string',
    };
  }

}
