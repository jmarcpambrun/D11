<?php

namespace Drupal\maestro_ai_task_assistant\Plugin\MaestroAiTaskCapabilities;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesInterface;
use Drupal\maestro_ai_task\MaestroAiTaskCapabilitiesPluginBase;
use Drupal\maestro_ai_task_assistant\ResponseNormalizer;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Delegates a Maestro AI Task's prompt to a configured AI Assistant.
 *
 * @MaestroAiTaskCapabilities(
 *   id = "MaestroAiTaskAssistantCapability",
 *   ai_provider = "chat",
 *   capability_description = @Translation("AI Assistant (tools/agents supported)."),
 * )
 */
class MaestroAiTaskAssistantCapability extends MaestroAiTaskCapabilitiesPluginBase implements MaestroAiTaskCapabilitiesInterface {

  /**
   * {@inheritDoc}
   */
  public function getMaestroAiTaskConfigFormElements(): array {
    $options = [];
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $assistant */
    foreach (AiAssistant::loadMultiple() as $id => $assistant) {
      $options[$id] = $assistant->label();
    }
    $task_ai = $this->task['data']['ai'] ?? [];
    return [
      'assistant_id' => [
        '#type' => 'select',
        '#title' => $this->t('AI Assistant'),
        '#description' => $this->t('The pre-configured AI Assistant this task will delegate its prompt to. Build or edit Assistants at /admin/config/ai/ai-assistant.'),
        '#options' => $options,
        '#empty_option' => $this->t('- Select an AI Assistant -'),
        '#default_value' => $task_ai['assistant_id'] ?? '',
        '#required' => TRUE,
      ],
      'post_process_prompt' => [
        '#type' => 'textarea',
        '#title' => $this->t('Post-processing instructions (optional)'),
        '#description' => $this->t('An AI Assistant can be configured with its own tools, agents, and system prompt, so its raw answer may not come back in the shape this task needs. If you provide instructions here, the Assistant\'s answer is passed through one additional, separate AI call with these instructions to reshape it for example "extract a JSON object with keys score and justification", "summarize in one sentence", or your own criteria for a yes/no decision. Leave this blank to use the Assistant\'s answer as-is.<br><br><strong>If "Return the data into..." below is set to "Process in task": </strong>the final answer (whatever produces it your instructions here, or the default described below) must be raw JSON shaped exactly like {"result":"true"} or {"result":"false"}, because Maestro decodes it directly and will fail the task otherwise. If you leave this field blank, a default instruction that performs that classification for you is used automatically. If you write your own instructions here, they take over completely and are entirely responsible for producing that shape nothing else classifies the answer afterward.'
        ),
        '#default_value' => $task_ai['post_process_prompt'] ?? '',
        '#required' => FALSE,
        '#attributes' => [
          'rows' => 6,
          'data-mdxeditor' => 'maestro_ai_task_assistant_post_process_prompt',
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function validateMaestroAiTaskEditForm(array &$form, FormStateInterface $form_state): void {
    $assistant_id = $form_state->getValue('assistant_id');
    if (!$assistant_id) {
      $form_state->setErrorByName('assistant_id', $this->t('You must select an AI Assistant.'));
      return;
    }
    if (!AiAssistant::load($assistant_id)) {
      $form_state->setErrorByName('assistant_id', $this->t('The selected AI Assistant no longer exists.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task): void {
    $task['data']['ai']['assistant_id'] = $form_state->getValue('assistant_id');
    $task['data']['ai']['post_process_prompt'] = self::unescapeMdxEditorArtifacts($form_state->getValue('post_process_prompt') ?? '');

    // MaestroAITask::prepareTaskForSave() already set ai_prompt directly from
    // its own MDX-editor-backed textarea before delegating to us; clean that
    // up here too so a Maestro token typed there survives without needing a
    // contrib patch.
    if (isset($task['data']['ai']['ai_prompt'])) {
      $task['data']['ai']['ai_prompt'] = self::unescapeMdxEditorArtifacts($task['data']['ai']['ai_prompt']);
    }
  }

  /**
   * Reverses the MDX/Markdown editor's escaping of literal special characters.
   *
   * The editor round-trips every edit through a Markdown parse/serialize
   * cycle, which unconditionally backslash-escapes '[', '_', '*' and '`'
   * wherever they appear in plain text, including inside a literal Maestro
   * token like [maestro:process-variable-value:node_id], breaking it. These
   * fields are never rendered as Markdown downstream (they're concatenated
   * into AI prompts and token-replaced as plain text).
   *
   * @param string $text
   *   The raw value from an MDX-editor-backed textarea.
   *
   * @return string
   *   The text with the editor's escaping undone.
   */
  protected static function unescapeMdxEditorArtifacts(string $text): string {
    return str_replace(['\\[', '\\_', '\\*', '\\`'], ['[', '_', '*', '`'], $text);
  }

  /**
   * {@inheritDoc}
   */
  public function performMaestroAiTaskValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task): void {
    $assistant_id = $task['data']['ai']['assistant_id'] ?? '';
    if (!$assistant_id) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('No AI Assistant has been selected for this task.'),
      ];
      return;
    }
    if (!AiAssistant::load($assistant_id)) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The AI Assistant "@id" selected for this task no longer exists.', ['@id' => $assistant_id]),
      ];
    }
  }

  /**
   * {@inheritDoc}
   *
   * FALSE so that MaestroAITask::execute() never appends its return-format
   * instruction onto $this->prompt before calling execute(). If it did,
   * that instruction would leak into ai_assistant_api's internal
   * pre-prompt/action-decision call (which shares the same user message as
   * the real request) and corrupt it. See execute()'s handling of
   * 'process_in_task' below for how boolean output is still supported
   * without relying on that shared mechanism.
   */
  public function allowConfigurableReturnFormat(): bool {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function execute(): ?string {
    $task_ai = $this->task['data']['ai'] ?? [];
    $assistant_id = $task_ai['assistant_id'] ?? NULL;
    if (!$assistant_id) {
      $this->taskStatus = TASK_STATUS_CANCEL;
      \Drupal::logger('maestro_ai_task_assistant')->error('No AI Assistant configured for this task.');
      return NULL;
    }

    $assistant = AiAssistant::load($assistant_id);
    if (!$assistant) {
      $this->taskStatus = TASK_STATUS_CANCEL;
      \Drupal::logger('maestro_ai_task_assistant')->error('AI Assistant "@id" no longer exists.', ['@id' => $assistant_id]);
      return NULL;
    }

    /** @var \Drupal\ai_assistant_api\AiAssistantApiRunner $runner */
    $runner = \Drupal::service('ai_assistant_api.runner');
    // ai_assistant_api.runner is a shared container service. A single
    // Maestro orchestrator run can execute several of these tasks back to
    // back in one request, and setAssistant() only regenerates a thread ID
    // when $threadId is currently empty so without this reset, a second
    // task in the same run could inherit the first task's leftover thread
    // and mix tempstore history/context between two unrelated executions.
    $runner->unsetThreadsKey();
    $runner->setAssistant($assistant);
    $runner->setUserMessage(new UserMessage($this->prompt));
    // Every ai_assistant_api process() call resolves a "current page title"
    // via the request's matched route (AssistantMessageBuilder::
    // getPrePromptDrupalContext(), which TitleResolver::getTitle() requires
    // a non-null Route for). A Maestro task has no page it runs during
    // orchestration, whether triggered via drush (no matched route at all,
    // which throws a TypeError) or Maestro's own HTTP orchestrator route
    // (a matched route, but an unrelated one). Seed Maestro's own real
    // orchestrator route unconditionally so the assistant's prompt context
    // is identical and non-crashing regardless of which of those triggered
    // this task, then restore whatever was there once the call is done so
    // this doesn't leak into anything else running in the same request.
    $orchestrator_route = $this->getOrchestratorRoute();
    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = \Drupal::service('request_stack');
    $current_request = $request_stack->getCurrentRequest();
    $original_route_object = $current_request?->attributes->get('_route_object');
    $current_request?->attributes->set('_route_object', $orchestrator_route);
    $runner->setContext(['current_route' => $orchestrator_route->getPath()]);
    $runner->setThrowException(TRUE);

    try {
      $response = $runner->process();
    }
    catch (\Exception $e) {
      $this->taskStatus = TASK_STATUS_CANCEL;
      \Drupal::logger('maestro_ai_task_assistant')->error('AI Assistant call failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
    finally {
      $current_request?->attributes->set('_route_object', $original_route_object);
    }

    $answer = ResponseNormalizer::normalize($response);

    // An Assistant can be configured with its own tools/agents/system
    // prompt, so its raw answer may not come back in whatever shape this
    // task actually needs that shaping is the template builder's
    // responsibility, not something we can guess. If they've written their
    // own post-processing instructions, those take over completely.
    $post_process_prompt = trim($task_ai['post_process_prompt'] ?? '');
    if ($post_process_prompt !== '') {
      return $this->postProcess($answer, $post_process_prompt);
    }

    // Otherwise, 'process_in_task' is the one Maestro return mode that
    // requires JSON shaped like {"result":"true"/"false"}
    // (MaestroAITask::execute()'s 'process_in_task' branch). Since
    // allowConfigurableReturnFormat() is FALSE, that shape was never
    // requested of the Assistant itself, so fall back to a default
    // instruction that performs the classification for the template
    // builder. This (like any post-processing instructions above) is a
    // second, plain chat call that never touches ai_assistant_api, so the
    // pre-prompt bug this whole method works around cannot recur here
    // either. process_variable/ai_task_entity tasks with no post-processing
    // instructions return the plain answer as-is.
    if (($task_ai['ai_return_into'] ?? '') === 'process_in_task') {
      return $this->postProcess($answer, self::DEFAULT_BOOLEAN_INSTRUCTIONS);
    }

    return $answer;
  }

  /**
   * Gets Maestro's own orchestrator route, to seed onto the request before
   * calling the Assistant runner (see execute()).
   *
   * Maestro's orchestrator route is core, load-bearing functionality (it's
   * how Maestro itself runs), so it's a safe fixture to depend on but the
   * lookup is still wrapped defensively: the whole point of seeding a route
   * here is to prevent execute() from crashing, so a failed lookup must
   * fall back rather than introduce a new way to crash.
   *
   * @return \Symfony\Component\Routing\Route
   *   Maestro's 'maestro.orchestrator' route, or a synthetic fallback.
   */
  protected function getOrchestratorRoute(): Route {
    /** @var \Drupal\Core\Routing\RouteProviderInterface $route_provider */
    $route_provider = \Drupal::service('router.route_provider');
    try {
      return $route_provider->getRouteByName('maestro.orchestrator');
    }
    catch (RouteNotFoundException $e) {
      return new Route('/maestro/orchestrator-task', ['_title' => 'Maestro Orchestrator Task']);
    }
  }

  /**
   * Default post-processing instructions used for 'process_in_task' when
   * the template builder hasn't written their own.
   */
  protected const DEFAULT_BOOLEAN_INSTRUCTIONS = 'Given the following answer, reply with raw JSON only (no formatting or code block markers), '
    . 'a single key-value pair with the key "result" and the value "true" for an affirmative/successful answer or '
    . '"false" for a negative/failure answer. Do not explain your answer.';

  /**
   * Reshapes a plain-language answer per the given instructions, via a
   * plain chat call.
   *
   * Deliberately bypasses ai_assistant_api (and therefore the Assistant's
   * own tools/agents) for this step it only needs to read $answer, not
   * reason with any tool, and going through the Assistant runner again
   * would re-trigger the same pre-prompt/action-decision mechanism this
   * class otherwise avoids.
   *
   * @param string $answer
   *   The Assistant's plain-language answer to reshape.
   * @param string $instructions
   *   Instructions for how to reshape $answer either the template
   *   builder's own "Post-processing instructions", or
   *   self::DEFAULT_BOOLEAN_INSTRUCTIONS.
   *
   * @return string
   *   The reshaped answer.
   */
  protected function postProcess(string $answer, string $instructions): string {
    $post_process_prompt = $instructions . "\n\n" . 'Answer: ' . $answer;

    /** @var \Drupal\ai\AiProviderPluginManager $service */
    $service = \Drupal::service('ai.provider');
    $sets = $service->getDefaultProviderForOperationType('chat');
    /** @var \Drupal\ai\OperationType\Chat\ChatInterface $provider */
    $provider = $service->createInstance($sets['provider_id']);
    $messages = new ChatInput([
      new ChatMessage('user', $post_process_prompt),
    ]);
    $message = $provider->chat($messages, $sets['model_id'], ['maestro-ai-task-assistant-post-process'])->getNormalized();
    return Xss::filter($message->getText());
  }

}
