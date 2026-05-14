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
 * Tool: add a comment (note) to a GitLab merge request.
 */
#[Tool(
  id: 'gitlab_api_add_mr_comment',
  label: new TranslatableMarkup('GitLab: add comment to merge request'),
  description: new TranslatableMarkup('Adds a comment (note) to a GitLab merge request.'),
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
    'mr_iid' => new InputDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Merge request IID'),
      description: new TranslatableMarkup('GitLab merge-request IID.'),
      default_value: '[event:mr_iid]',
      required: TRUE,
    ),
    'body' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Body'),
      description: new TranslatableMarkup('Comment text (markdown).'),
      required: TRUE,
    ),
  ],
  output_definitions: [
    'mr_iid' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Merge request IID'),
      default_value: '[output:mr_iid]',
      description: 'Output Token: IID of the commented merge request.',
    ),
  ],
)]
final class AddMrComment extends GitlabIssueToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $project = $this->resolveProject((string) $values['project']);
    if ($project === NULL) {
      return ExecutableResult::failure($this->t('Unknown project @id.', ['@id' => $values['project']]));
    }
    $iid = (int) $values['mr_iid'];
    $body = (string) $values['body'];
    if ($iid <= 0 || $body === '') {
      return ExecutableResult::failure($this->t('Invalid MR IID @iid or empty body.', ['@iid' => $iid]));
    }
    try {
      $this->clientFactory->forProject($project)
        ->mergeRequests()
        ->addNote($project->getGitLabProjectId(), $iid, $body);
    }
    catch (\Throwable $e) {
      return $this->failWithLog('Add comment to MR #@iid failed: @msg', [
        '@iid' => $iid,
        '@msg' => $e->getMessage(),
      ]);
    }
    return ExecutableResult::success(
      $this->t('Added comment to MR #@iid.', ['@iid' => $iid]),
      ['mr_iid' => $iid]
    );
  }

}
