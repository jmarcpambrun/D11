<?php

namespace Drupal\ai_ckeditor\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\Response\AiStreamedResponse;
use Drupal\ai_ckeditor\AiCKEditorRequestTags;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for CKEditor integration routes.
 */
class AiRequest implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $pluginManager
   *   AI CKEditor Plugin manager.
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderManager
   *   AI Provider manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   Account proxy.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle info service.
   */
  public function __construct(
    protected readonly AiCKEditorPluginManager $pluginManager,
    protected readonly AiProviderPluginManager $aiProviderManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $account,
    LoggerChannelFactoryInterface $logger_factory,
    protected readonly MessengerInterface $messenger,
    protected readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {
    $this->logger = $logger_factory->get('ai_ckeditor');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_ckeditor'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * Performs a request to AI for streamed output.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor.
   * @param \Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface $ai_ckeditor_plugin
   *   The CK Editor plugin.
   *
   * @return \Drupal\ai\Response\AiStreamedResponse|\Symfony\Component\HttpFoundation\Response
   *   The AI response.
   */
  public function doRequest(Request $request, EditorInterface $editor, AiCKEditorPluginInterface $ai_ckeditor_plugin): AiStreamedResponse|Response {
    $data = json_decode($request->getContent());

    // Extract and validate entity context from the request payload. The
    // editor's dialog JS sets these from drupalSettings, which is populated
    // server-side via hook_form_alter on the host entity form. This avoids
    // fragile URL parsing and handles nested/AJAX-loaded forms. The bundle
    // only matters for an unsaved entity, which has no id yet. The page
    // path is where the editor is embedded; the request itself hits this
    // API endpoint, so subscribers cannot read it from the route.
    $entity_context = $this->validateEntityContext(
      (string) ($data->entity_type ?? ''),
      (string) ($data->entity_id ?? ''),
      (string) ($data->entity_bundle ?? ''),
    );
    $page_path = $this->normalizePagePath(
      (string) ($data->page_path ?? ''),
      $request->getBasePath(),
    );
    if ($page_path !== NULL) {
      $entity_context = ($entity_context ?? []) + ['path' => $page_path];
    }

    try {
      $settings = $editor->getSettings();
      $configuration = $settings["plugins"]["ai_ckeditor_ai"]["plugins"];
      $preferred_model = $configuration[$ai_ckeditor_plugin->getPluginId()]['provider'];

      if ($preferred_model) {
        $ai_provider = $this->aiProviderManager->loadProviderFromSimpleOption($preferred_model);
        $ai_model = $this->aiProviderManager->getModelNameFromSimpleOption($preferred_model);
      }
      else {
        // Get the default provider.
        $default_provider = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
        if (empty($default_provider['provider_id'])) {
          // If we got nothing return NULL.
          $this->messenger->addError($this->t('No AI provider is set for chat. Please configure one in the "Text format and editors settings" or setup a default Chat model in the %ai_settings_link.', [
            '%ai_settings_link' => Link::createFromRoute($this->t('AI settings'), 'ai.settings_form')
              ->toString(),
          ]));
          throw new \exception('No AI provider is set for chat. Please configure one in the AI default settings or in the ai_content settings form.');
        }
        $ai_provider = $this->aiProviderManager->createInstance($default_provider['provider_id']);
        $ai_model = $default_provider['model_id'];
      }

      // @todo Check config if user wants answers as HTML.
      /** @var \Drupal\filter\FilterFormatInterface $format */
      $format = $editor->getFilterFormat();
      $restrictions = $format->getHtmlRestrictions();

      // Supply the permitted HTML tags to the provider.
      if (!empty($restrictions) && !empty($restrictions['allowed'])) {
        $allowed_tags = "";

        foreach ($restrictions['allowed'] as $tag => $metadata) {
          $allowed_tags .= $tag . " ";
        }

        $data->prompt = "Format the answer using ONLY the following HTML tags: " . $allowed_tags . $data->prompt;
      }
      else {
        $data->prompt = "Format the answer using basic HTML formatting tags." . $data->prompt;
      }
      $data->prompt = "Do not try to use any image, video, or audio tags. Do not use backticks or ```html indicator." . $data->prompt;

      // Build the system prompt.
      $system_prompt = 'You are a helpful website assistant for content writing and editing.';
      $system_prompt .= ' Do not give responses in the first, second or third person form. Do not add any commentary to the answer.';

      $messages = new ChatInput([
        new ChatMessage('user', $data->prompt),
      ]);

      // Add the system message.
      $messages->setStreamedOutput(TRUE);
      $messages->setSystemPrompt($system_prompt);

      // Attach entity context as directed event metadata. Subscribers to the
      // generic PreGenerateResponseEvent can read this via
      // $event->getMetadata('entity_context') to do bundle-scoped context
      // injection without any ai_ckeditor-specific wiring.
      if ($entity_context !== NULL) {
        $messages->setRequestMetadataValue('entity_context', $entity_context);
      }

      /** @var \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $response */
      $response = $ai_provider->chat(
        $messages,
        $ai_model,
        AiCKEditorRequestTags::forPlugin($ai_ckeditor_plugin->getPluginId()),
      )->getNormalized();

      if ($response instanceof StreamedChatMessageIteratorInterface) {
        return new AiStreamedResponse(function () use ($response) {
          foreach ($response as $message) {
            echo $message->getText();
            flush();
          }
        });
      }
      else {
        return new Response($response->getText());
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }

    return new Response("The request could not be completed.", Response::HTTP_BAD_REQUEST);
  }

  /**
   * Validates the entity context payload from the client.
   *
   * @param string $entity_type
   *   The submitted entity type id.
   * @param string $entity_id
   *   The submitted entity id (integer for content entities, string for
   *   config entities). Empty for an entity that is not saved yet.
   * @param string $bundle
   *   The submitted bundle. Only used when $entity_id is empty; for a
   *   saved entity the bundle is read from the loaded entity instead.
   *
   * @return array|null
   *   An array with entity_type, bundle, and id keys, or NULL if nothing
   *   valid was submitted. For an unsaved entity the id is an empty
   *   string, the type must be a content entity, and the bundle must
   *   exist and be creatable by the user.
   */
  protected function validateEntityContext(string $entity_type, string $entity_id, string $bundle = ''): ?array {
    if ($entity_type === '') {
      return NULL;
    }
    if (!$this->entityTypeManager->hasDefinition($entity_type)) {
      return NULL;
    }
    if ($entity_id === '') {
      return $this->validateUnsavedEntityContext($entity_type, $bundle);
    }
    // Validate by loading instead of checking ID format. This supports
    // both content entities (integer IDs) and config entities (string IDs)
    // and confirms the entity actually exists.
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if ($entity === NULL || !$entity->access('view')) {
      return NULL;
    }
    // Use the loaded entity's bundle rather than trusting the client value.
    return [
      'entity_type' => $entity_type,
      'bundle' => $entity->bundle(),
      'id' => $entity_id,
    ];
  }

  /**
   * Validates entity context for an entity that has no id yet.
   *
   * On an add form there is nothing to load, so the bundle is checked
   * against bundle info and against create access for the current user.
   * Config entity types are rejected. The result is a matching hint
   * only; it never grants access.
   *
   * @param string $entity_type
   *   A known entity type id.
   * @param string $bundle
   *   The submitted bundle.
   *
   * @return array|null
   *   An array with entity_type, bundle, and an empty id, or NULL when
   *   the type is not a content entity, the bundle is unknown, or the
   *   user may not create it.
   *
   * @internal
   */
  protected function validateUnsavedEntityContext(string $entity_type, string $bundle): ?array {
    if ($bundle === '') {
      return NULL;
    }
    $definition = $this->entityTypeManager->getDefinition($entity_type);
    if (!$definition instanceof ContentEntityTypeInterface) {
      return NULL;
    }
    $bundles = $this->bundleInfo->getBundleInfo($entity_type);
    if (!isset($bundles[$bundle])) {
      return NULL;
    }
    $access_bundle = $definition->hasKey('bundle') ? $bundle : NULL;
    $access = $this->entityTypeManager
      ->getAccessControlHandler($entity_type)
      ->createAccess($access_bundle, $this->account);
    if (!$access) {
      return NULL;
    }
    return [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'id' => '',
    ];
  }

  /**
   * Normalizes the page path hint sent by the editor.
   *
   * The value is untrusted client input used only for string matching
   * by subscribers. It is never used to load anything or to redirect.
   * The editor sends window.location.pathname unchanged and this is
   * the only place the hint is normalized, so the site base path is
   * never removed twice. Percent-encoded segments are decoded so the
   * result matches the decoded Drupal path that Site Sections patterns
   * are written against. The site base path and a leading /index.php
   * (no-clean-URL front controller) are removed so those patterns
   * still match on subdirectory installs and sites without clean URLs.
   *
   * Structural checks run again after decoding. Encoded NUL (%00)
   * and encoded slashes that would become "//" are rejected.
   *
   * @param string $path
   *   The submitted path.
   * @param string $base_path
   *   The request base path, such as "/sub". Empty or "/" is ignored.
   *
   * @return string|null
   *   A path beginning with a single "/", without query string,
   *   fragment, site base path, or /index.php, or NULL when the
   *   value is not usable.
   *
   * @internal
   */
  protected function normalizePagePath(
    string $path,
    string $base_path = '',
  ): ?string {
    $path = trim($path);
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
      return NULL;
    }
    $path = explode('?', $path, 2)[0];
    $path = explode('#', $path, 2)[0];
    // Bound the raw hint before decode so a huge payload is dropped.
    if ($path === '' || strlen($path) > 2048) {
      return NULL;
    }
    $path = rawurldecode($path);
    $path = explode('?', $path, 2)[0];
    $path = explode('#', $path, 2)[0];
    if (
      $path === ''
      || $path[0] !== '/'
      || str_starts_with($path, '//')
      || str_contains($path, "\0")
      || strlen($path) > 2048
    ) {
      return NULL;
    }
    if ($base_path !== '' && $base_path !== '/') {
      if ($path === $base_path) {
        $path = '/';
      }
      elseif (str_starts_with($path, $base_path . '/')) {
        $path = substr($path, strlen($base_path)) ?: '/';
      }
    }
    if ($path === '/index.php') {
      return '/';
    }
    if (str_starts_with($path, '/index.php/')) {
      $path = substr($path, strlen('/index.php')) ?: '/';
    }
    return $path;
  }

}
