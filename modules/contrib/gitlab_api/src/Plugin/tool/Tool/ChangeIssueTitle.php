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
 * Tool: change a GitLab issue title.
 */
#[Tool(
  id: 'gitlab_api_change_issue_title',
  label: new TranslatableMarkup('GitLab: change issue title'),
  description: new TranslatableMarkup('Updates the title of a GitLab issue.'),
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
    'title' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Title'),
      description: new TranslatableMarkup('New issue title.'),
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
final class ChangeIssueTitle extends GitlabIssueToolBase {

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $project = $this->resolveProject((string) $values['project']);
    if ($project === NULL) {
      return ExecutableResult::failure($this->t('Unknown project @id.', ['@id' => $values['project']]));
    }
    $iid = (int) $values['issue_iid'];
    $title = trim((string) $values['title']);
    if ($iid <= 0 || $title === '') {
      return ExecutableResult::failure($this->t('Invalid IID @iid or empty title.', ['@iid' => $iid]));
    }
    try {
      $this->clientFactory->forProject($project)
        ->issues()
        ->update($project->getGitLabProjectId(), $iid, ['title' => $title]);
    }
    catch (\Throwable $e) {
      return $this->failWithLog('Change title on issue #@iid failed: @msg', [
        '@iid' => $iid,
        '@msg' => $e->getMessage(),
      ]);
    }
    return ExecutableResult::success(
      $this->t('Changed title of issue #@iid.', ['@iid' => $iid]),
      ['issue_iid' => $iid]
    );
  }

}
