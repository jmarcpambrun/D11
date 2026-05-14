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
 * Tool: replace the body / description of a GitLab issue.
 */
#[Tool(
  id: 'gitlab_api_update_issue_body',
  label: new TranslatableMarkup('GitLab: update issue body'),
  description: new TranslatableMarkup('Replaces the description body of a GitLab issue.'),
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
    'body' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Body'),
      description: new TranslatableMarkup('New issue description (markdown).'),
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
final class UpdateIssueBody extends GitlabIssueToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $project = $this->resolveProject((string) $values['project']);
    if ($project === NULL) {
      return ExecutableResult::failure($this->t('Unknown project @id.', ['@id' => $values['project']]));
    }
    $iid = (int) $values['issue_iid'];
    $body = (string) $values['body'];
    if ($iid <= 0) {
      return ExecutableResult::failure($this->t('Invalid issue IID @iid.', ['@iid' => $iid]));
    }
    try {
      $this->clientFactory->forProject($project)
        ->issues()
        ->update($project->getGitLabProjectId(), $iid, ['description' => $body]);
    }
    catch (\Throwable $e) {
      return $this->failWithLog('Update body on issue #@iid failed: @msg', [
        '@iid' => $iid,
        '@msg' => $e->getMessage(),
      ]);
    }
    return ExecutableResult::success(
      $this->t('Updated body of issue #@iid.', ['@iid' => $iid]),
      ['issue_iid' => $iid]
    );
  }

}
