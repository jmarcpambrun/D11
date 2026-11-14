<?php

namespace Drupal\ai_integration_eca\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageInterface;
use Drupal\ai\PluginManager\ChatProcessorPluginManager;
use Drupal\ai_integration_eca\Exception\ChatProviderUnavailableException;
use Drupal\ai_integration_eca\Service\ModelerContextBuilderInterface;
use Drupal\ai_integration_eca\Service\ModelerPlanValidator;
use Drupal\ai_integration_eca\Service\ModelerResponseParserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Answers questions about the model currently open in the modeler.
 */
class ModelerAssistantController extends ControllerBase {

  /**
   * The chat processor driving the assistant.
   *
   * A site that wants different behavior can replace the plugin class through
   * hook_chat_processor_info_alter(), which the AI module provides for exactly
   * this purpose.
   */
  public const PROCESSOR_ID = 'ai_integration_eca_modeler_assistant';

  /**
   * Maximum number of previous messages accepted from the browser.
   */
  protected const MAX_HISTORY_MESSAGES = 20;

  /**
   * Maximum number of characters accepted for a single message.
   */
  protected const MAX_MESSAGE_CHARACTERS = 4000;

  /**
   * Maximum number of graph identifiers read out of one snapshot.
   *
   * The snapshot comes from the browser, so the number of things a plan
   * could be checked against is not something to take on trust.
   */
  protected const MAX_GRAPH_IDENTIFIERS = 5000;

  /**
   * The chat processor plugin manager.
   *
   * @var \Drupal\ai\PluginManager\ChatProcessorPluginManager
   */
  protected ChatProcessorPluginManager $chatProcessorManager;

  /**
   * The modeler context builder.
   *
   * @var \Drupal\ai_integration_eca\Service\ModelerContextBuilderInterface
   */
  protected ModelerContextBuilderInterface $contextBuilder;

  /**
   * The assistant response parser.
   *
   * @var \Drupal\ai_integration_eca\Service\ModelerResponseParserInterface
   */
  protected ModelerResponseParserInterface $responseParser;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->chatProcessorManager = $container->get('plugin.manager.ai.chat_processor');
    $instance->contextBuilder = $container->get('ai_integration_eca.modeler_context_builder');
    $instance->responseParser = $container->get('ai_integration_eca.modeler_response_parser');
    $instance->logger = $container->get('logger.channel.ai_integration_eca');

    return $instance;
  }

  /**
   * Handles a chat request coming from the modeler panel.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response. On success it carries the "reply", "threadId",
   *   "suggestions" and "operations" keys, the last two of which are empty
   *   lists when the answer proposed nothing. On failure it carries an
   *   "error" key instead.
   */
  public function chat(Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return $this->error($this->t('The request could not be read.'), 400);
    }

    $prompt = is_scalar($payload['prompt'] ?? NULL) ? trim((string) $payload['prompt']) : '';
    if ($prompt === '') {
      return $this->error($this->t('Please enter a question.'), 400);
    }
    if (mb_strlen($prompt) > self::MAX_MESSAGE_CHARACTERS) {
      return $this->error($this->t('The question is too long. Please shorten it to @count characters or less.', [
        '@count' => self::MAX_MESSAGE_CHARACTERS,
      ]), 400);
    }

    if (!$this->chatProcessorManager->hasDefinition(self::PROCESSOR_ID)) {
      return $this->error($this->t('The AI assistant is not available because its chat processor is missing.'), 500);
    }

    $snapshot = is_array($payload['snapshot'] ?? NULL) ? $payload['snapshot'] : [];
    $context = $this->contextBuilder->build($snapshot, $prompt);
    $threadId = $this->resolveThreadId($payload['threadId'] ?? NULL);

    $messages = $this->buildMessages($payload['history'] ?? NULL, $context, $prompt);

    try {
      /** @var \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface $processor */
      $processor = $this->chatProcessorManager->createInstance(self::PROCESSOR_ID);

      $processor->setInput(new ChatInput($messages));
      $processor->setThreadId($threadId);
      $processor->execute();
      $reply = $this->extractReply($processor->getOutput());
    }
    catch (ChatProviderUnavailableException) {
      return $this->error($this->t('The AI assistant is not available because no default chat provider is configured for the "chat" operation type. Ask an administrator to configure one.'), 503);
    }
    catch (\Throwable $exception) {
      $this->logger->error('The modeler AI assistant failed: @message', [
        '@message' => $exception->getMessage(),
      ]);
      return $this->error($this->t('The AI assistant could not answer this question. Please try again, and check the logs if the problem persists.'), 500);
    }

    if ($reply === '') {
      return $this->error($this->t('The AI assistant returned an empty answer. Please try again.'), 502);
    }

    $parsed = $this->responseParser->parse($reply, $this->graphContext($snapshot));
    $prose = $parsed['reply'];
    $suggestions = $parsed['suggestions'];
    $operations = $parsed['operations'];

    // An answer made of nothing but the structured block leaves no prose
    // behind once the block has been taken out of it. When it carried usable
    // suggestions or a plan they still deserve a line to sit under; when it
    // did not, the answer was empty in every way that matters to the user.
    if ($prose === '' && $suggestions === [] && $operations === []) {
      return $this->error($this->t('The AI assistant returned an empty answer. Please try again.'), 502);
    }
    if ($prose === '') {
      $prose = (string) $this->t('These components may fit the next step.');
    }

    // A refused plan is not an error. The user still gets the answer, plus a
    // sentence saying why nothing was offered to apply, because silently
    // dropping a plan the assistant described in its prose would read as the
    // panel being broken.
    $refusal = $this->planRefusal($parsed['operationsReason']);
    if ($refusal !== '') {
      $prose .= "\n\n" . $refusal;
    }

    return new JsonResponse([
      'reply' => $prose,
      'threadId' => $threadId,
      'suggestions' => $suggestions,
      'operations' => $operations,
    ]);
  }

  /**
   * Collects what a proposed plan is allowed to refer to.
   *
   * @param array $snapshot
   *   The graph snapshot as sent by the browser.
   *
   * @return array
   *   The "nodes", "edges" and "plugins" identifiers.
   */
  protected function graphContext(array $snapshot): array {
    return [
      'nodes' => $this->identifiers($snapshot['nodes'] ?? NULL, 'id'),
      'edges' => $this->identifiers($snapshot['edges'] ?? NULL, 'id'),
      'plugins' => $this->identifiers($snapshot['components'] ?? NULL, 'plugin'),
    ];
  }

  /**
   * Reads one identifier out of each entry of a snapshot list.
   *
   * @param mixed $items
   *   The raw list from the snapshot.
   * @param string $key
   *   The key holding the identifier.
   *
   * @return array
   *   The identifiers, without duplicates.
   */
  protected function identifiers(mixed $items, string $key): array {
    if (!is_array($items)) {
      return [];
    }

    $identifiers = [];
    foreach ($items as $item) {
      if (count($identifiers) >= self::MAX_GRAPH_IDENTIFIERS) {
        break;
      }
      if (!is_array($item)) {
        continue;
      }
      $identifier = $item[$key] ?? NULL;
      if (!is_scalar($identifier) || is_bool($identifier)) {
        continue;
      }
      $identifier = trim((string) $identifier);
      if ($identifier !== '') {
        $identifiers[$identifier] = $identifier;
      }
    }

    return array_values($identifiers);
  }

  /**
   * Explains why a proposed plan was not offered.
   *
   * @param string $reason
   *   The reason as reported by the plan validator.
   *
   * @return string
   *   The sentence to add to the answer, or an empty string when the plan
   *   was fine or none was proposed.
   */
  protected function planRefusal(string $reason): string {
    $refusal = match ($reason) {
      ModelerPlanValidator::REASON_REFERENCE => $this->t('A plan was proposed but not offered, because it referred to something that is not on the canvas.'),
      ModelerPlanValidator::REASON_PLUGIN => $this->t('A plan was proposed but not offered, because it used a component this site does not have.'),
      ModelerPlanValidator::REASON_SIZE => $this->t('A plan was proposed but not offered, because it was too long to review in one go. Try asking for one part of it at a time.'),
      ModelerPlanValidator::REASON_SHAPE => $this->t('A plan was proposed but not offered, because it could not be read.'),
      default => NULL,
    };

    return $refusal === NULL ? '' : (string) $refusal;
  }

  /**
   * Builds the conversation sent to the processor.
   *
   * The model description is attached to the newest user message rather than
   * to every one of them, so that a long conversation does not repeat the
   * whole graph once per turn.
   *
   * @param mixed $history
   *   The previous messages as sent by the browser.
   * @param string $context
   *   The description of the current model.
   * @param string $prompt
   *   The question the user just asked.
   *
   * @return array
   *   The chat messages.
   */
  protected function buildMessages(mixed $history, string $context, string $prompt): array {
    $messages = [];

    if (is_array($history)) {
      // Only the most recent exchanges are worth the tokens.
      $history = array_slice($history, -self::MAX_HISTORY_MESSAGES);
      foreach ($history as $message) {
        if (!is_array($message)) {
          continue;
        }
        $role = is_string($message['role'] ?? NULL) ? $message['role'] : '';
        $text = $message['text'] ?? '';
        if (!in_array($role, ['user', 'assistant'], TRUE) || !is_scalar($text)) {
          continue;
        }
        $text = trim((string) $text);
        if ($text === '') {
          continue;
        }
        $messages[] = new ChatMessage($role, mb_substr($text, 0, self::MAX_MESSAGE_CHARACTERS));
      }
    }

    $messages[] = new ChatMessage('user', implode("\n", [
      'This is the model currently open on the canvas.',
      '',
      $context,
      '',
      'This is the question about that model.',
      '',
      $prompt,
    ]));

    return $messages;
  }

  /**
   * Validates the thread ID, or creates one.
   *
   * The thread ID comes from the browser and is passed on to the processor,
   * which may use it to look up server side conversation state. Only a
   * generated looking value is accepted so that it cannot be used to reach
   * into somebody else's conversation by guessing a readable identifier.
   *
   * @param mixed $threadId
   *   The thread ID as sent by the browser.
   *
   * @return string
   *   A usable thread ID.
   */
  protected function resolveThreadId(mixed $threadId): string {
    if (is_string($threadId) && preg_match('/^[0-9a-f]{32}$/', $threadId) === 1) {
      return $threadId;
    }

    return bin2hex(random_bytes(16));
  }

  /**
   * Reads the answer out of the processor output.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput|null $output
   *   The processor output.
   *
   * @return string
   *   The answer, or an empty string when there is none.
   */
  protected function extractReply(?ChatOutput $output): string {
    if ($output === NULL) {
      return '';
    }
    $normalized = $output->getNormalized();
    if ($normalized instanceof ChatMessage) {
      return trim($normalized->getText());
    }

    // Streaming is not offered by this endpoint, but a processor may still
    // hand back an iterator. Collect it into a single answer.
    $text = '';
    foreach ($normalized as $chunk) {
      if ($chunk instanceof StreamedChatMessageInterface) {
        $text .= $chunk->getText();
      }
    }

    return trim($text);
  }

  /**
   * Builds an error response.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message shown to the user.
   * @param int $status
   *   The HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  protected function error(TranslatableMarkup $message, int $status): JsonResponse {
    return new JsonResponse(['error' => (string) $message], $status);
  }

}
