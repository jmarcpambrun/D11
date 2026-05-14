<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Plugin\tool\Tool;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;

/**
 * Tool: set the weight of a GitLab issue.
 */
#[Tool(
  id: 'gitlab_api_set_issue_weight',
  label: new TranslatableMarkup('GitLab: set issue weight'),
  description: new TranslatableMarkup('Sets the weight (Premium/Ultimate) of a GitLab issue.'),
  operation: ToolOperation::Write,
  destructive: FALSE,
  input_definitions: [
    'project' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Project'),
      description: new TranslatableMarkup('GitlabProject config entity ID.'),
      default_value: '[event:project_id]',
      required: TRUE,
    ),
    'issue_iid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Issue IID'),
      description: new TranslatableMarkup('GitLab issue IID.'),
      default_value: '[event:issue_iid]',
      required: TRUE,
    ),
    'weight' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Weight'),
      description: new TranslatableMarkup('Non-negative integer weight.'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'issue_iid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Issue IID'),
      default_value: '[output:issue_iid]',
      description: 'Output Token: IID of the updated issue.',
    ),
  ],
)]
final class SetIssueWeight extends GitlabIssueToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $project = $this->resolveProject((string) $values['project']);
    if ($project === NULL) {
      return ExecutableResult::failure($this->t('Unknown project @id.', ['@id' => $values['project']]));
    }
    $iid = (int) $values['issue_iid'];
    $weight = (int) $values['weight'];
    if ($iid <= 0 || $weight < 0) {
      return ExecutableResult::failure($this->t('Invalid IID @iid or weight @w.', ['@iid' => $iid, '@w' => $weight]));
    }
    try {
      $this->clientFactory->forProject($project)
        ->issues()
        ->update($project->getGitLabProjectId(), $iid, ['weight' => $weight]);
    }
    catch (\Throwable $e) {
      return $this->failWithLog('Set weight @w on issue #@iid failed: @msg', [
        '@w' => $weight,
        '@iid' => $iid,
        '@msg' => $e->getMessage(),
      ]);
    }
    return ExecutableResult::success(
      $this->t('Set issue #@iid weight=@w.', ['@iid' => $iid, '@w' => $weight]),
      ['issue_iid' => $iid]
    );
  }

}
