<?php

namespace Drupal\ai_integration_eca_agents\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A form to propose a question to AI.
 */
class AskAiForm extends FormBase {

  /**
   * The id of the AI agent that handles ECA.
   */
  protected const AGENT_ID = 'eca';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_integration_eca_agents_ask_ai';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['question'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Propose your question'),
      '#description' => $this->t('Describe the model that should be created, or ask a question about the existing models, events, conditions and actions. Existing models can not be changed.'),
      '#required' => TRUE,
    ];

    // Determine the destination for when the batch process is finished.
    $destination = $this->getRequest()->query->get('destination', Url::fromRoute('entity.eca.collection')->toString());
    $form['destination'] = [
      '#type' => 'value',
      '#value' => $destination,
    ];

    // Determine if an existing model is the subject of the prompt.
    $modelId = $this->getRequest()->query->get('model-id');
    $form['model_id'] = [
      '#type' => 'value',
      '#value' => $modelId,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask'),
      '#button_type' => 'primary',
    ];
    $form['#theme'] = 'confirm_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Solving question.'));
    $batch->setInitMessage($this->t('Asking AI...'));
    $batch->setErrorMessage($this->t('An error occurred during processing.'));
    $batch->setFinishCallback([self::class, 'batchFinished']);

    $batch->addOperation([self::class, 'runAgent'], [
      $form_state->getValue('question'),
      $form_state->getValue('model_id'),
      $form_state->getValue('destination'),
    ]);

    batch_set($batch->toArray());
  }

  /**
   * Batch operation that runs the ECA agent.
   *
   * @param string $question
   *   The question of the user.
   * @param string|null $modelId
   *   The ECA model the user is looking at, if any.
   * @param string $destination
   *   The destination for when the process is finished.
   * @param array $context
   *   The context.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public static function runAgent(string $question, ?string $modelId, string $destination, array &$context): void {
    $context['message'] = t('Asking AI...');
    $context['results']['destination'] = $destination;

    if (!empty($modelId)) {
      $question = sprintf("%s\n\nThe user is currently looking at the ECA model with the id \"%s\".", $question, $modelId);
    }

    /** @var \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent */
    $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance(self::AGENT_ID);
    $agent->setChatHistory([new ChatMessage('user', $question)]);

    try {
      $agent->determineSolvability();
      $context['results']['response'] = $agent->solve();
    }
    catch (\Exception $e) {
      \Drupal::logger('ai_integration_eca_agents')->error('The ECA agent failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      $context['results']['error'] = t('The AI agent could not solve the task: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Callback for when the batch is finished.
   *
   * @param bool $success
   *   A boolean indicating a successful execution.
   * @param array $results
   *   The collection of results.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Returns a http-response.
   */
  public static function batchFinished(bool $success, array $results): Response {
    if (!empty($results['error'])) {
      \Drupal::messenger()->addError($results['error']);
    }
    if (!empty($results['response'])) {
      \Drupal::messenger()->addStatus($results['response']);
    }
    elseif (empty($results['error'])) {
      \Drupal::messenger()->addWarning(t('The AI agent did not come back with an answer. Please try again with a more detailed description.'));
    }

    return new RedirectResponse($results['destination']);
  }

}
