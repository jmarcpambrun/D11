<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\GenericFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_integration_eca\Service\AiFileHelperInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the AI ai_integration_eca_execute_chat action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_chat",
 *   label = @Translation("Chat"),
 *   description = @Translation("Run text through the AI chat model.")
 * )
 */
class Chat extends AiConfigActionBase {

  /**
   * The file helper.
   *
   * @var \Drupal\ai_integration_eca\Service\AiFileHelperInterface
   */
  protected AiFileHelperInterface $fileHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileHelper = $container->get('ai_integration_eca.file_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $defaults = parent::defaultConfiguration();
    unset($defaults['token_input']);
    return $defaults + [
      'system_prompt' => '',
      'prompt' => 'Enter your prompt for AI here.',
      'schema' => '',
      'files' => '',
      'files_input_type' => AiFileHelperInterface::INPUT_PATH,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    unset($form['token_input']);

    $form['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#default_value' => $this->configuration['system_prompt'],
      '#description' => $this->t('An optional instruction that sets the behavior of the model, such as "You are a helpful assistant". It is sent as the system prompt of the request, next to the prompt below, and supports the same tokens.'),
    ];
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->configuration['prompt'],
      '#description' => $this->t('Enter your text here. When submitted, AI will generate a response from its Chats endpoint. Based on the complexity of your text, AI traffic, and other factors, a response can sometimes take up to 10-15 seconds to complete. Please allow the operation to finish. Be cautious not to exceed the requests per minute quota (20/Minute by default), or you may be temporarily blocked.'),
      '#required' => TRUE,
    ];
    $form['schema'] = [
      '#type' => 'ai_json_schema',
      '#title' => $this->t('Schema'),
      '#default_value' => $this->configuration['schema'],
      '#description' => $this->t('Provide an optional JSON schema to request structured output. When set, the model is asked to return JSON matching the schema, and that JSON is stored as text in the token specified in "Token result". Follow OpenAI\'s structured-output JSON Schema format: %link', [
        '%link' => Link::fromTextAndUrl($this->t('JSON Schema'), Url::fromUri('https://platform.openai.com/docs/guides/structured-outputs#examples', [
          'attributes' => ['target' => '_blank'],
        ]))->toString(),
      ]),
    ];

    $form['files'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Files'),
      '#default_value' => $this->configuration['files'],
      '#description' => $this->t('Optional file(s) to attach to the prompt. Accepts a single token holding a list of files (e.g. <em>[my_files]</em> or <em>[node:field_images]</em>), a single token holding one file, or a value typed in directly. Every value is read as configured in "Input source" below.'),
      // Not "#eca_token_reference": that flag contracts the field to the bare
      // name of an ECA token, and the modeler refuses "[name]" for it. This
      // field also takes a literal value and a Drupal token, both of which
      // have to be written out in full.
      '#eca_token_replacement' => TRUE,
    ];
    $form['files_input_type'] = $this->fileHelper->inputElement($this->configuration['files_input_type']);
    $form['files_input_type']['#description'] = $this->t('How each value in "Files" should be interpreted. All attached files are read the same way.');

    // The parent describes the field in the abstract; add an example of what
    // such a provider setting looks like in this YAML.
    $form['config']['#description'] = $this->t('@description<br/>Eg. <pre>temperature: 0.5</pre>', [
      '@description' => $form['config']['#description'],
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    unset($this->configuration['token_input']);

    $this->configuration['system_prompt'] = $form_state->getValue('system_prompt');
    $this->configuration['prompt'] = $form_state->getValue('prompt');
    $this->configuration['schema'] = $form_state->getValue('schema');
    $this->configuration['files'] = $form_state->getValue('files');
    $this->configuration['files_input_type'] = $form_state->getValue('files_input_type');
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    $schema = (string) $form_state->getValue('schema');
    if ($schema === '') {
      return;
    }

    try {
      json_decode($schema, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      $form_state->setErrorByName('schema', $this->t('The schema must be valid JSON. @message', [
        '@message' => $exception->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $modelData = $this->getModelData();
    try {
      $provider = $this->loadModelProvider();
    }
    catch (PluginException $e) {
      return;
    }

    $prompt = $this->tokenService->replace($this->configuration['prompt']);

    // Resolving attachments happens before the request is built: a prompt that
    // was written to talk about a file is misleading without it, so a file that
    // cannot be read aborts the action rather than silently sending a
    // text-only request.
    try {
      $files = $this->resolveFiles();
    }
    catch (\InvalidArgumentException) {
      return;
    }

    $userMessage = new ChatMessage('user', $prompt);
    foreach ($files as $file) {
      $userMessage->setFile($file);
    }
    $messages = new ChatInput([
      $userMessage,
    ]);
    $systemPrompt = (string) $this->tokenService->replace($this->configuration['system_prompt']);
    if ($systemPrompt !== '') {
      $messages->setSystemPrompt($systemPrompt);
    }
    $provider->setConfiguration($this->getModelConfig());
    $schema = $this->configuration['schema'];
    if ($schema !== '') {
      try {
        $schemaArray = json_decode($schema, TRUE, 512, JSON_THROW_ON_ERROR);
        $messages->setChatStructuredJsonSchema($schemaArray);
      }
      catch (\JsonException) {
        // @todo Ignored for now.
      }
    }
    $message = $provider->chat($messages, $modelData['model_id'])->getNormalized();
    $response = trim($message->getText());
    if ($response === '') {
      $response = $this->t('No result could be generated.');
    }

    $this->tokenService->addTokenData($this->configuration['token_result'], $response);
  }

  /**
   * Resolves the configured file references into AI file objects.
   *
   * @return \Drupal\ai\OperationType\GenericType\FileBaseInterface[]
   *   The files to attach to the chat message, in configured order.
   *
   * @throws \InvalidArgumentException
   *   When a configured file cannot be read.
   */
  protected function resolveFiles(): array {
    $inputType = (string) ($this->configuration['files_input_type'] ?? '');
    if ($inputType === '') {
      $inputType = AiFileHelperInterface::INPUT_PATH;
    }

    $files = [];
    foreach ($this->resolveFileValues() as $value) {
      $file = $this->fileHelper->resolveInput($value, $inputType, GenericFile::class);
      // Raw binary carries no filename to guess a MIME type from, and every
      // provider needs one to decide how (or whether) to send the file.
      if ($file->getMimeType() === '') {
        $file->setMimeType($this->guessMimeTypeFromBinary($file->getBinary()));
      }
      // Providers dispatch on the concrete class - an ImageFile becomes an
      // image part, anything else a document/file part - so the generic object
      // the helper returns has to be re-typed once its MIME type is known.
      // MIME types are case-insensitive per RFC 2045, hence the normalization.
      if (str_starts_with(strtolower($file->getMimeType()), 'image/')) {
        $file = new ImageFile($file->getBinary(), $file->getMimeType(), $file->getFilename());
      }
      $files[] = $file;
    }
    return $files;
  }

  /**
   * Reads the "files" configuration into a list of raw file references.
   *
   * The field holds one of three things: a lone token that resolves to a list
   * of files, a lone token that resolves to a single file, or a literal value
   * (which may still contain tokens). Only the lone-token form can produce more
   * than one file; anything else names exactly one.
   *
   * @return string[]
   *   The raw references (file IDs, paths/URIs or binaries), empty ones
   *   removed.
   */
  protected function resolveFileValues(): array {
    $value = $this->resolveTokenValue((string) ($this->configuration['files'] ?? ''));
    return $value === NULL ? [] : $this->flattenFileValues($value);
  }

  /**
   * Flattens a token value into a list of raw file references.
   *
   * @param mixed $data
   *   The token data.
   *
   * @return string[]
   *   The raw references, empty ones removed.
   */
  protected function flattenFileValues(mixed $data): array {
    // Entities and scalars always describe a single file, even though a content
    // entity is itself traversable (over its fields).
    if (!$data instanceof EntityInterface && (is_array($data) || $data instanceof \Traversable)) {
      $values = [];
      foreach ($data as $item) {
        $value = $this->fileValueToString($item);
        if ($value !== '') {
          $values[] = $value;
        }
      }
      if ($values !== []) {
        return $values;
      }
    }

    // Not a list, or a list that yielded nothing: a DTO built from a scalar,
    // for instance, has no properties but does have a string representation.
    $value = $this->fileValueToString($data);
    return $value === '' ? [] : [$value];
  }

  /**
   * Reads a single list item as a raw file reference.
   *
   * @param mixed $value
   *   The item.
   *
   * @return string
   *   The reference, or an empty string when the item holds none.
   */
  protected function fileValueToString(mixed $value): string {
    // A file or image field item: take the referenced ID rather than the item's
    // own string form, which concatenates every property it has.
    if ($value instanceof EntityReferenceItem) {
      return (string) ($value->target_id ?? '');
    }
    // Items of a list are handed over as they are held, so unlike a token read
    // whole, one wrapping an entity still has to be unwrapped here.
    if (!$value instanceof EntityInterface && $value instanceof TypedDataInterface) {
      $inner = $value->getValue();
      if ($inner instanceof EntityInterface) {
        return (string) $inner->id();
      }
    }
    return $this->tokenValueToString($value);
  }

  /**
   * Guesses a MIME type from file contents.
   *
   * @param string $binary
   *   The file contents.
   *
   * @return string
   *   The MIME type, or an empty string when it cannot be determined. The
   *   fileinfo extension is not required by Drupal core, so its absence is a
   *   supported outcome rather than an error.
   */
  protected function guessMimeTypeFromBinary(string $binary): string {
    if ($binary === '' || !class_exists('finfo')) {
      return '';
    }
    try {
      // The object API, rather than finfo_open()/finfo_close(): the latter is
      // deprecated as of PHP 8.4, and the handle is released with the object.
      $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
    }
    catch (\Exception) {
      // Constructing the reader fails when the magic database is unreadable.
      return '';
    }
    return is_string($mimeType) ? $mimeType : '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'chat';
  }

}
