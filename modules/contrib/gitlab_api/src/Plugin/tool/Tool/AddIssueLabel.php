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
 * Tool: add one or more labels to a GitLab issue.
 */
#[Tool(
  id: 'gitlab_api_add_issue_label',
  label: new TranslatableMarkup('GitLab: add label(s) to issue'),
  description: new TranslatableMarkup('Adds one or more comma-separated labels to a GitLab issue.'),
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
    'labels' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Labels'),
      description: new TranslatableMarkup('Comma-separated label names (e.g. "bug, priority::high").'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'issue_iid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Issue IID'),
      default_value: '[output:issue_iid]',
      description: 'Output Token: IID of the labelled issue.',
    ),
  ],
)]
final class AddIssueLabel extends GitlabIssueToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $project = $this->resolveProject((string) $values['project']);
    if ($project === NULL) {
      return ExecutableResult::failure($this->t('Unknown project @id.', ['@id' => $values['project']]));
    }
    $iid = (int) $values['issue_iid'];
    if ($iid <= 0) {
      return ExecutableResult::failure($this->t('Invalid issue IID @iid.', ['@iid' => $iid]));
    }
    $labels = array_values(array_filter(
      array_map('trim', explode(',', (string) $values['labels'])),
      static fn (string $s): bool => $s !== '',
    ));
    if ($labels === []) {
      return ExecutableResult::failure($this->t('No labels provided.'));
    }
    $joined = implode(',', $labels);
    try {
      $this->clientFactory->forProject($project)
        ->issues()
        ->update($project->getGitLabProjectId(), $iid, ['add_labels' => $joined]);
    }
    catch (\Throwable $e) {
      return $this->failWithLog('Add labels "@l" failed for issue #@iid: @msg', [
        '@l' => $joined,
        '@iid' => $iid,
        '@msg' => $e->getMessage(),
      ]);
    }
    return ExecutableResult::success(
      $this->t('Added labels "@l" to issue #@iid.', ['@l' => $joined, '@iid' => $iid]),
      ['issue_iid' => $iid]
    );
  }

}
