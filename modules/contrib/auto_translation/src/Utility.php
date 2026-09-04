<?php

namespace Drupal\auto_translation;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Google\Cloud\Translate\V2\TranslateClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Utility class for auto_translation module functions.
 *
 * This class provides secure, optimized translation services with proper
 * HTML handling, caching, and support for multiple translation providers.
 *
 * @package Drupal\auto_translation
 */
class Utility {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * Maximum text length for translation.
   */
  const MAX_TEXT_LENGTH = 10000;

  /**
   * Cache TTL for translations (24 hours).
   */
  const CACHE_TTL = 86400;

  /**
   * HTML parsing regex pattern.
   */
  const HTML_PARSE_PATTERN = '/(<[^>]*>)|([^<]+)/';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new Utility object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    MessengerInterface $messenger,
    LanguageManagerInterface $language_manager,
    ModuleHandlerInterface $module_handler,
    CacheBackendInterface $cache_backend,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->messenger = $messenger;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->cacheBackend = $cache_backend;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Dependency injection via create().
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('messenger'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get('cache.default'),
      $container->get('logger.factory')
    );
  }

  /**
   * Translates text using the configured translation provider.
   *
   * This is the main entry point for all translation operations.
   * It automatically handles both plain text and HTML content securely.
   *
   * @param string $text
   *   The text to translate.
   * @param string $source_language
   *   The source language code.
   * @param string $target_language
   *   The target language code.
   *
   * @return string
   *   The translated text or original text if translation fails.
   */
  public function translate($text, $source_language, $target_language) {
    // Early validation
    if (!$this->isValidTranslationRequest($text, $source_language, $target_language)) {
      return $text;
    }

    $config = $this->configFactory->get('auto_translation.settings');
    $provider = $config->get('auto_translation_provider') ?? 'google';

    // Check cache first
    $cache_key = $this->generateCacheKey($text, $source_language, $target_language, $provider);
    $cached = $this->cacheBackend->get($cache_key);
    if ($cached && !empty($cached->data)) {
      return $cached->data;
    }

    // Determine if content is HTML and translate accordingly
    $translation = $this->containsHtml($text)
      ? $this->translateHtmlSafely($text, $source_language, $target_language, $provider)
      : $this->translatePlainText($text, $source_language, $target_language, $provider);

    // Cache successful translation
    if ($translation && $translation !== $text) {
      $this->cacheBackend->set($cache_key, $translation, time() + self::CACHE_TTL, ['auto_translation']);
    }

    return $translation ?? $text;
  }

  /**
   * Validates if the translation request is valid.
   *
   * @param string $text
   *   The text to validate.
   * @param string $source_language
   *   The source language code.
   * @param string $target_language
   *   The target language code.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  private function isValidTranslationRequest($text, $source_language, $target_language) {
    // Check for empty or invalid input
    if (empty(trim($text)) || strlen($text) > self::MAX_TEXT_LENGTH) {
      return FALSE;
    }

    // Validate language codes
    if (empty($source_language) || empty($target_language) || $source_language === $target_language) {
      return FALSE;
    }

    // Security check - ensure text is safe
    $validation = $this->validateTranslationInput($text);
    return $validation['valid'];
  }

  /**
   * Generates a cache key for translation requests.
   *
   * @param string $text
   *   The text to translate.
   * @param string $source_language
   *   The source language code.
   * @param string $target_language
   *   The target language code.
   * @param string $provider
   *   The translation provider.
   *
   * @return string
   *   The cache key.
   */
  private function generateCacheKey($text, $source_language, $target_language, $provider) {
    return 'auto_translation:' . md5($text . $source_language . $target_language . $provider);
  }

  /**
   * Checks if text contains HTML tags.
   *
   * @param string $text
   *   The text to check.
   *
   * @return bool
   *   TRUE if text contains HTML, FALSE otherwise.
   */
  private function containsHtml($text) {
    return strip_tags($text) !== $text;
  }

  /**
   * Translates HTML content safely by extracting text and preserving structure.
   *
   * @param string $html
   *   The HTML content to translate.
   * @param string $source_language
   *   The source language code.
   * @param string $target_language
   *   The target language code.
   * @param string $provider
   *   The translation provider.
   *
   * @return string
   *   The translated HTML content.
   */
  private function translateHtmlSafely($html, $source_language, $target_language, $provider) {
    // Use the existing safe text extraction method
    return $this->translateHtmlWithSafeTextExtraction($html, $source_language, $target_language, $provider);
  }

  /**
   * Translates plain text using the specified provider.
   *
   * @param string $text
   *   The plain text to translate.
   * @param string $source_language
   *   The source language code.
   * @param string $target_language
   *   The target language code.
   * @param string $provider
   *   The translation provider.
   *
   * @return string|null
   *   The translated text or NULL if translation fails.
   */
  private function translatePlainText($text, $source_language, $target_language, $provider) {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    // Validate and sanitize input
    $validation = $this->validateTranslationInput($text);
    if (!$validation['valid']) {
      if ($enable_debug) {
        $logger->warning('Plain text validation failed: @error', ['@error' => $validation['error']]);
      }
      return NULL;
    }

    $processed_text = $validation['text'];

    // Apply additional security for non-Google providers
    if ($provider !== 'google' || $config->get('auto_translation_api_enabled')) {
      $processed_text = Html::escape($processed_text);
    }

    try {
      $translation = $this->callProviderTranslationApi($processed_text, $source_language, $target_language, $provider);

      if ($enable_debug && $translation) {
        $logger->info('Plain text translation successful with @provider', ['@provider' => $provider]);
      }

      return $translation;
    } catch (\Exception $e) {
      $logger->error('Plain text translation error: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Translates the given text using the API deepl translate server.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */
  public function deeplTranslateApiCall($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $translation = NULL;
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('DeepL API Call - Processing plain text directly');
    }

    // This method now only handles plain text - HTML is handled centrally
    $deeplMode = $config->get('auto_translation_api_deepl_pro_mode') === false ? 'api-free' : 'api';
    $endpoint = sprintf('https://%s.deepl.com/v2/translate', $deeplMode);
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);

    $options = [
      'headers' => [
        'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'text' => [$text],
        'source_lang' => $s_lang,
        'target_lang' => $t_lang,
      ],
      'verify' => FALSE,
    ];

    $maxRetries = 8;
    $startTime = microtime(true);

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
      try {
        $response = $this->httpClient->post($endpoint, $options);
        $result = Json::decode($response->getBody()->getContents());
        $translation = htmlspecialchars_decode($result['translations'][0]['text']) ?? NULL;
        break;
      }
      catch (RequestException $e) {
        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

        if ($statusCode == 429 && $attempt < $maxRetries - 1) {
          $elapsed = microtime(true) - $startTime;
          $waitTime = min(60, 0.5 * ($attempt + 1) ** 2 + mt_rand(0, 1000) / 1000.0); // max 60s
          if ($enable_debug) {
            $logger->warning('DeepL API 429 - Retry @attempt after @wait seconds (elapsed: @elapsed)', [
              '@attempt' => $attempt + 1,
              '@wait' => round($waitTime, 2),
              '@elapsed' => round($elapsed, 2),
            ]);
          }
          sleep((int) $waitTime);
          continue;
        }

        $logger->error('Translation API error: @error', ['@error' => $e->getMessage()]);
        $this->messenger->addError($this->t('Translation API error: @error', ['@error' => $e->getMessage()]));
        break;
      }
    }

    return $translation;
  }

  /**
   * Translates the given text using the API libre translate server.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */
  public function libreTranslateApiCall($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $translation = NULL;
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('LibreTranslate API Call - Processing plain text directly');
    }

    // This method now only handles plain text - HTML is handled centrally
    $endpoint = 'https://libretranslate.com/translate';
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);

    $options = [
      'headers' => ['Content-Type' => 'application/json'],
      'json' => [
        'q' => $text,
        'source' => $s_lang,
        'target' => $t_lang,
        'format' => 'text',
        'api_key' => $apiKey,
      ],
      'verify' => FALSE,
    ];

    try {
      $response = $this->httpClient->post($endpoint, $options);
      $result = Json::decode($response->getBody()->getContents());
      $translation = $result['translatedText'] ?? NULL;
    }
    catch (RequestException $e) {
      $this->getLogger('auto_translation')->error('Translation API error: @error', ['@error' => $e->getMessage()]);
      $this->messenger->addError($this->t('Translation API error: @error', ['@error' => $e->getMessage()]));
    }

    return $translation;
  }

  /**
   * Translates the given text using the API Drupal AI translate server.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */
  public function drupalAiTranslateApiCall($text, $s_lang, $t_lang) {
    $logger = $this->getLogger('auto_translation');
    $translation = NULL;

    // Load debug configuration
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    if (!$this->moduleHandler->moduleExists('ai') && !$this->moduleHandler->moduleExists('ai_translate')) {
      $logger->error('Auto translation error: AI Module not installed please install Drupal AI module');
      return [
        '#type' => 'markup',
        '#markup' => $this->t('AI Module not installed please install Drupal AI and Drupal AI Translate modules'),
      ];
    }
    if (!\Drupal::service('ai.provider')->hasProvidersForOperationType('chat', TRUE) && !\Drupal::service('ai.provider')->hasProvidersForOperationType('translate', TRUE)) {
      $markupError = $this->t('To use Drupal AI to translate you need to configure a provider for the operation type "translate" or "chat" in the <a href=":url" target="_blank">Drupal AI module providers section</a>.', [':url' => '/admin/config/ai/providers']);
      return [
        '#type' => 'markup',
        '#markup' => $markupError,
      ];
    }

    if ($enable_debug) {
      $logger->info('Drupal AI API Call - Processing plain text directly');
    }

    // This method now only handles plain text - HTML is handled centrally
    $container = $this->getContainer();
    $languageManager = $container->get('language_manager');
    $langFrom = $languageManager->getLanguage($s_lang);
    $langTo = $languageManager->getLanguage($t_lang);

    try {
      $translatedText = \Drupal::service('ai_translate.text_translator')->translateContent($text, $langTo, $langFrom);
      return $translatedText;
    }
    catch (RequestException $exception) {
      $logger->error('Auto translation error: @error', ['@error' => json_encode($exception->getMessage())]);
      $this->getMessages($exception->getMessage());
      return $exception;
    }
  }

  /**
   * Translates the given text using the API server.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */

  /**
   * Calls the Google API to translate text using server-side key.
   */
  public function translateApiServerCall($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $translation = NULL;
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('Google Server API Call - Processing plain text directly');
    }

    // This method now only handles plain text - HTML is handled centrally
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);
    // Create a new TranslateClient object.
    $client = new TranslateClient([
      'key' => $apiKey,
    ]);

    try {
      $result = $client->translate($text, ['source' => $s_lang, 'target' => $t_lang]);
      $translation = htmlspecialchars_decode($result['text']);
    }
    catch (RequestException $e) {
      $this->getLogger('auto_translation')->error('Auto translation error: @error', ['@error' => $e->getMessage()]);
      $this->messenger->addError($this->t('Translation API error: @error', ['@error' => $e->getMessage()]));
    }

    return $translation;
  }

  /**
   * Translates the given text using the API browser call.
   *
   * @param string $text
   *   The text to be translated.
   * @param string $s_lang
   *   The source language of the text.
   * @param string $t_lang
   *   The target language for the translation.
   *
   * @return string
   *   The translated text.
   */
  public function translateApiBrowserCall($text, $s_lang, $t_lang) {
    $translation = NULL;
    $logger = $this->loggerFactory->get('auto_translation');

    $logger->info('Google Browser API Call - Processing plain text directly');

    // This method now only handles plain text - HTML is handled centrally
    $endpoint = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . $s_lang . '&tl=' . $t_lang . '&dt=t&q=' . rawurlencode($text);
    $options = [
      'headers' => [
        'User-Agent' => 'Drupal Auto Translation Module/1.0',
      ],
      'timeout' => 30,
      'connect_timeout' => 10,
    ];

    try {
      $response = $this->httpClient->get($endpoint, $options);
      $data = Json::decode($response->getBody()->getContents());

      $translation = '';
      foreach ($data[0] as $segment) {
        $translation .= htmlspecialchars_decode($segment[0]) ?? '';
      }
    }
    catch (RequestException $e) {
      $this->getLogger('auto_translation')->error('Translation API error: @error', ['@error' => $e->getMessage()]);
      $this->messenger->addError($this->t('Translation API error: @error', ['@error' => $e->getMessage()]));
    }

    return $translation;
  }

  /**
   * Custom function to return saved resources.
   */
  public function getEnabledContentTypes() {
    $config = $this->config();
    $enabledContentTypes = $config->get('auto_translation_content_types') ? $config->get('auto_translation_content_types') : NULL;

    // Handle backward compatibility: convert old format to new format if needed
    if ($enabledContentTypes && is_array($enabledContentTypes)) {
      $converted = [];
      foreach ($enabledContentTypes as $key => $value) {
        // If the value doesn't contain a colon, it's the old format
        if (strpos($value, ':') === FALSE) {
          // Try to guess the entity type based on common patterns
          // This is a best-effort conversion for backward compatibility
          if (in_array($value, ['node', 'media', 'block_content', 'taxonomy_term', 'webform'])) {
            // This is likely an entity type itself, skip it
            continue;
          }
          // For bundle IDs without prefixes, we need to check what entity types exist
          // This is a fallback - ideally the configuration should be updated
          $converted['node:' . $value] = 'node:' . $value;
        } else {
          // This is already in the new format
          $converted[$key] = $value;
        }
      }
      return $converted;
    }

    return $enabledContentTypes;
  }

  /**
   * Retrieves the excluded fields.
   *
   * @return array
   *   The excluded fields.
   */
  public function getExcludedFields() {
    $config = $this->config();
    $excludedFields = [
      '0',
      '1',
      '#access',
      'behavior_settings',
      'boolean',
      'changed',
      'code',
      'content_translation_outdated',
      'content_translation_source',
      'content_translation_status',
      'created',
      'datetime',
      'default_langcode',
      'draft',
      'language',
      'langcode',
      'moderation_state',
      'parent_field_name',
      'parent_type',
      'path',
      'promote',
      'published',
      'ready for review',
      'revision_timestamp',
      'revision_uid',
      'status',
      'sticky',
      'uid',
      'und',
      'unpublished',
      'uuid',
      NULL,
    ];
    $excludedFieldsSettings = $config->get('auto_translation_excluded_fields') ? $config->get('auto_translation_excluded_fields') : [];
    if ($excludedFieldsSettings) {
      $excludedFieldsSettings = explode(",", $excludedFieldsSettings);
      $excludedFields = array_merge($excludedFields, $excludedFieldsSettings);
      $excludedFields['user_settings'] = $excludedFieldsSettings ?? [];
    }
    return $excludedFields;
  }

  /**
   * Implements auto translation for an entity form.
   *
   * Automatically translates translatable fields and nested paragraph fields
   * when adding a translation for an entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The modified form array.
   */
  public function formTranslate(&$form = NULL, &$form_state = NULL, &$entity = NULL, &$t_lang = NULL, &$d_lang = NULL, &$action = NULL, &$chunk = NULL) {
    if ($this->hasPermission() === FALSE) {
      return;
    }
    $current_path = \Drupal::service('path.current')->getPath();
    $enabledContentTypes = $this->getEnabledContentTypes();

    // Retrieve the entity from the form state if not provided.
    if (!$entity && $form_state && $form_state->getFormObject()) {
      $form_object = $form_state->getFormObject();
      if ($form_object instanceof \Drupal\Core\Entity\EntityFormInterface) {
        $entity = $form_object->getEntity();
      }
    }
    if (!$entity || !$entity instanceof ContentEntityInterface) {
      $this->getLogger('auto_translation')->error($this->t('Translation error: Entity not found.'));
      return;
    }
    else {
      if (empty($form) && ($entity instanceof ContentEntityInterface)) {
        $batch = new BatchBuilder();
        // Set up the batch.
        $batch->setTitle($this->t('Processing entity auto translation in batch'))
          ->setInitMessage($this->t('Starting entity auto translation'))
          ->setProgressMessage($this->t('Processed @current out of @total.'))
          ->setErrorMessage($this->t('An error occurred during entity auto translation.'))
          ->setFinishCallback([get_class($this), 'batchFinishedCallback']);

        $entity_type = $entity->getEntityTypeId();
        if (!$entity->hasTranslation($t_lang)) {
          // Verify entity type of type 'node' or 'media'.
          if ($entity_type == 'node') {
            $title = $entity->get('title')->value;
            $t_title = $this->translate($title, $d_lang, $t_lang);
            $entity->addTranslation($t_lang, ['title' => $t_title]);
          }
          elseif ($entity_type == 'media') {
            $title = $entity->get('name')->value;
            $t_title = $this->translate($title, $d_lang, $t_lang);
            $entity->addTranslation($t_lang, ['name' => $t_title]);
          }
          else {
            // Handle other entity types if needed.
          }
        }
        else {
          // Prevent translation if it already exists.
          $this->getLogger('auto_translation')->notice($this->t('Translation error: Translation already exists.'));
          return;
        }
      }
    }

    if (!$entity instanceof ContentEntityInterface) {
      $this->getLogger('auto_translation')->error($this->t('Translation error: Entity is not a content entity.'));
      return;
    }

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $excludedFields = $this->getExcludedFields();
    $languageManager = \Drupal::service('language_manager');

    // Create the prefixed bundle identifier for checking enabled content types
    $prefixed_bundle = $entity_type . ':' . $bundle;

    if ($enabledContentTypes && (strpos($current_path, 'translations/add') !== FALSE || ($entity && $t_lang && $d_lang)) && in_array($prefixed_bundle, $enabledContentTypes)) {
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
      $chunk_fields = array_chunk(range(1, 1000), count($fields));
      $t_lang = $t_lang ?: ($this->getTranslationLanguages($entity)['translated'] ?? $languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());
      $d_lang = $d_lang ?: ($this->getTranslationLanguages($entity)['original'] ?? $languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());
      // In batch mode, we work on the translation via batch operations.
      if (!$form && !$form_state) {
        // If the translation does not exist yet, add it.
        if (!$entity->hasTranslation($t_lang)) {
          // Verify entity type of type 'node' or 'media'.
          if ($entity_type == 'node') {
            $title = $entity->get('title')->value;
            $t_title = $this->translate($title, $d_lang, $t_lang);
            $entity->addTranslation($t_lang, ['title' => $t_title]);
          }
          elseif ($entity_type == 'media') {
            $title = $entity->get('name')->value;
            $t_title = $this->translate($title, $d_lang, $t_lang);
            $entity->addTranslation($t_lang, ['name' => $t_title]);
          }
          else {
            // Handle other entity types if needed.
          }
        }
        // Iterate over all fields to build batch operations.
        foreach ($fields as $field) {
          $field_name = $field->getName();
          $field_type = $field->getType();

          if ($entity_type === 'media' && $field_name === 'thumbnail') {
            $batch->addOperation(
              [self::class, 'batchCopyExcludedField'],
              [$entity->id(), $entity_type, $field_name, $d_lang, $t_lang]
            );
            continue;
          }

          // Check if field is in user-defined excluded fields
          $isUserExcluded = isset($excludedFields['user_settings']) &&
                           is_array($excludedFields['user_settings']) &&
                           in_array($field_name, $excludedFields['user_settings']);

          if (!in_array($field_name, $excludedFields) && !$isUserExcluded) {
            // Batch operation for simple translatable fields.
            if ($field->isTranslatable()) {
              $batch->addOperation(
                [self::class, 'batchTranslateField'],
                [$entity->id(), $entity_type, $field_name, $d_lang, $t_lang, $action]
              );
            }
            // Batch operation for paragraph fields.
            if ($this->isParagraphReference($field)) {
              if ($this->moduleHandler->moduleExists('paragraphs')) {
                $batch->addOperation(
                  [self::class, 'batchTranslateParagraphs'],
                  [$entity->id(), $entity_type, $field_name, $d_lang, $t_lang, $excludedFields, $action]
                );
              }
            }
          } elseif ($isUserExcluded) {
            // Add batch operation for excluded fields that should copy original values
            $batch->addOperation(
              [self::class, 'batchCopyExcludedField'],
              [$entity->id(), $entity_type, $field_name, $d_lang, $t_lang]
            );
          }
        }
        $batch->addOperation(
          [self::class, 'batchFinalizeEntity'],
          [$entity->id(), $entity_type, $t_lang, $action, $chunk_fields]
        );
        // Run the batch.
        $batch = $batch->toArray();

        batch_set($batch);
      }
      // Synchronous processing for form-based context.
      else {
        foreach ($fields as $field) {
          $field_name = $field->getName();
          $field_type = $field->getType();
          if ($field->isTranslatable() && !in_array($field_name, $excludedFields)) {
            if (isset($form[$field_name]['widget'])) {
              $this->translateField($entity, $form[$field_name]['widget'], $field_name, $field_type, $d_lang, $t_lang);
            }
            if (isset($form['name']) && !empty($form['name'])) {
              $this->translateField($entity, $form['name'], $field_name, $field_type, $d_lang, $t_lang);
            }
          }
          if ($this->isParagraphReference($field) && !in_array($field_name, $excludedFields)) {
            if ($this->moduleHandler->moduleExists('paragraphs')) {
              $this->translateParagraphs($entity, $form, $field_name, $d_lang, $t_lang, $excludedFields);
            }
          }
        }
        return $form;
      }
    }
  }

  /**
   * Batch callback: Copies original field values for excluded fields.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name.
   * @param string $d_lang
   *   The default language.
   * @param string $t_lang
   *   The target language.
   * @param array $context
   *   The batch context.
   */
  public static function batchCopyExcludedField($entity_id, $entity_type, $field_name, $d_lang, $t_lang, &$context) {
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    $auto_translate_service = \Drupal::service('auto_translation.utility');

    if (!$entity instanceof ContentEntityInterface) {
      $auto_translate_service->getLogger('auto_translation')->error($auto_translate_service->t('Translation error: Entity is not a content entity.'));
      return;
    }

    // Ensure the translation exists
    if (!$entity->hasTranslation($t_lang)) {
      // Create basic translation if it doesn't exist
      if ($entity_type == 'node') {
        $title = $entity->get('title')->value;
        $t_title = $auto_translate_service->translate($title, $d_lang, $t_lang);
        $entity->addTranslation($t_lang, ['title' => $t_title]);
      }
      elseif ($entity_type == 'media') {
        $title = $entity->get('name')->value;
        $t_title = $auto_translate_service->translate($title, $d_lang, $t_lang);
        $entity->addTranslation($t_lang, ['name' => $t_title]);
      }
      else {
        $entity->addTranslation($t_lang, []);
      }
      $entity->save();
    }

    $translated_entity = $entity->getTranslation($t_lang);
    $original_entity = $entity->getTranslation($d_lang);

    // Copy the original field value to the translated entity
    if ($entity->hasField($field_name) && $original_entity->hasField($field_name)) {
      $original_value = $original_entity->get($field_name)->getValue();
      $translated_entity->set($field_name, $original_value);
      $translated_entity->save();

      $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Copied excluded field @field from @d_lang to @t_lang', [
        '@field' => $field_name,
        '@d_lang' => $d_lang,
        '@t_lang' => $t_lang,
      ]));
    }

    $context['message'] = $auto_translate_service->t('Copied excluded field @field', ['@field' => $field_name]);
  }

  /**
   * Batch callback: Translates a simple translatable field.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name.
   * @param string $d_lang
   *   The default language.
   * @param string $t_lang
   *   The target language.
   * @param array $context
   *   The batch context.
   */
  public static function batchTranslateField($entity_id, $entity_type, $field_name, $d_lang, $t_lang, $action, &$context) {
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    $auto_translate_service = \Drupal::service('auto_translation.utility');

    if (!$entity instanceof ContentEntityInterface) {
      $auto_translate_service->getLogger('auto_translation')->error($auto_translate_service->t('Translation error: Entity is not a content entity.'));
      return;
    }

    if (!$entity->hasTranslation($t_lang)) {
      // Create the translation if it does not exist.
      // Verify entity type of type 'node' or 'media'.
      if ($entity_type == 'node') {
        $title = $entity->get('title')->value;
        $t_title = $auto_translate_service->translate($title, $d_lang, $t_lang);
        $entity->addTranslation($t_lang, ['title' => $t_title]);
      }
      elseif ($entity_type == 'media') {
        $title = $entity->get('name')->value;
        $t_title = $auto_translate_service->translate($title, $d_lang, $t_lang);
        $entity->addTranslation($t_lang, ['name' => $t_title]);
      }
      else {
        // Handle other entity types if needed.
      }
      $entity->save();
    }
    $excludedFields = $auto_translate_service->getExcludedFields();
    $translated_entity = $entity->getTranslation($t_lang);
    if (!isset($context['results'][$t_lang]) && $entity->hasField('title') && $entity_type == 'node') {
      $t_title = $translated_entity->get('title')->value;
      $context['results'][$t_lang] = '(' . $auto_translate_service->t('ID') . ': ' . $entity_id . '; ' . $auto_translate_service->t('Target Language:') . ' ' . $t_lang . ') - ' . $t_title;
    }
    if (!isset($context['results'][$t_lang]) && $entity->hasField('name') && $entity_type == 'media') {
      $t_name = $translated_entity->get('name')->value;
      $context['results'][$t_lang] = '(' . $auto_translate_service->t('ID') . ': ' . $entity_id . '; ' . $auto_translate_service->t('Target Language:') . ' ' . $t_lang . ') - ' . $t_name;
    }
    // Retrieve the field value.
    // Translate the value (using your translation service).
    $field = $entity->get($field_name);

    // Check if this field should be excluded - more precise check for user_settings
    $isUserExcluded = isset($excludedFields['user_settings']) &&
                     is_array($excludedFields['user_settings']) &&
                     in_array($field_name, $excludedFields['user_settings']);

    if (!$field->getFieldDefinition()->isTranslatable() ||
        in_array($field_name, $excludedFields) ||
        $isUserExcluded ||
        str_starts_with($field->getFieldDefinition()->getType(), 'list_')) {
      // Return original language value if not translatable or excluded.
      return;
    }
    if ($field->getFieldDefinition()->isTranslatable()) {

      // Retrieve the field values.
      $field_values = $entity->get($field_name)->getValue();
      // Iterate over each item to translate it.
      foreach ($field_values as &$item) {
        // Check exclusion again for safety
        $isUserExcluded = isset($excludedFields['user_settings']) &&
                         is_array($excludedFields['user_settings']) &&
                         in_array($field_name, $excludedFields['user_settings']);

        if (!$field->getFieldDefinition()->isTranslatable() ||
            in_array($field_name, $excludedFields) ||
            $isUserExcluded ||
            str_starts_with($field->getFieldDefinition()->getType(), 'list_')) {
          continue;
        }

        switch ($field->getFieldDefinition()->getType()) {
          case 'link':
            if (!empty($item['title']) && is_string($item['title'])) {
              $item['title'] = $auto_translate_service->translate($item['title'], $d_lang, $t_lang);
            }
            $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['title']);
            break;

          case 'image':
            if (!empty($item['alt']) && is_string($item['alt'])) {
              $item['alt'] = $auto_translate_service->translate($item['alt'], $d_lang, $t_lang);
              $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['alt']);
            }
            if (!empty($item['title']) && is_string($item['title'])) {
              $item['title'] = $auto_translate_service->translate($item['title'], $d_lang, $t_lang);
              $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['title']);
            }
            break;

          case 'file':
            if (!empty($item['description']) && is_string($item['description'])) {
              $item['description'] = $auto_translate_service->translate($item['description'], $d_lang, $t_lang);
              $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['description']);
            }
            break;

          case 'entity_reference':
            // Handle entity reference fields (taxonomy terms, nodes, etc.)
            $auto_translate_service->translateEntityReferenceField($item, $field, $d_lang, $t_lang);
            break;

          case 'entity_reference_revisions':
            // Handle entity reference revisions (excluding paragraphs which are handled separately)
            if ($field->getFieldDefinition()->getSetting('target_type') !== 'paragraph') {
              $auto_translate_service->translateEntityReferenceField($item, $field, $d_lang, $t_lang);
            }
            break;

          default:
            foreach ($item as $key => $value) {
              if (is_string($value) && !empty($value) && ($key === 'value' || $key === '#value' || $key === 'summary' || $key === 'summary_override' || $key === '#default_value')) {
                $item[$key] = $auto_translate_service->translate($value, $d_lang, $t_lang);
                $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item[$key]);
              }
              else {
                $item[$key] = $value;
              }
            }
            break;
        }
      }
      // Set the translated value in the translated paragraph.
      $translated_entity->set($field_name, $field_values);
      $translated_entity->save();
    }
    $context['message'] = $auto_translate_service->t('Translated field @field', ['@field' => $field_name]);
  }

  /**
   * Translates entity reference fields (taxonomy terms, nodes, etc.).
   *
   * This method handles translation of referenced entities by creating
   * or finding existing translations of the referenced content.
   *
   * @param array &$item
   *   The field item array to process.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field definition.
   * @param string $d_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   */
  private function translateEntityReferenceField(array &$item, $field, $d_lang, $t_lang) {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    // Get the target entity type from field settings
    $target_type = $field->getFieldDefinition()->getSetting('target_type');

    if (empty($item['target_id']) || !in_array($target_type, ['taxonomy_term', 'node', 'media', 'paragraph', 'block_content', 'webform'])) {
      return; // Only handle supported entity types
    }

    try {
      $entity_storage = \Drupal::entityTypeManager()->getStorage($target_type);
      $referenced_entity = $entity_storage->load($item['target_id']);

      if (!$referenced_entity || !$referenced_entity instanceof ContentEntityInterface) {
        return;
      }

      // Check if the referenced entity is translatable
      if (!$referenced_entity->getEntityType()->isTranslatable()) {
        if ($enable_debug) {
          $logger->debug('Entity reference field @field: Referenced @type entity @id is not translatable', [
            '@field' => $field->getName(),
            '@type' => $target_type,
            '@id' => $item['target_id'],
          ]);
        }
        return;
      }

      // Check if translation already exists
      if ($referenced_entity->hasTranslation($t_lang)) {
        $translated_entity = $referenced_entity->getTranslation($t_lang);
        $item['target_id'] = $translated_entity->id();

        if ($enable_debug) {
          $logger->debug('Entity reference field @field: Using existing translation for @type entity @id', [
            '@field' => $field->getName(),
            '@type' => $target_type,
            '@id' => $item['target_id'],
          ]);
        }
        return;
      }

      // Create new translation for the referenced entity
      $this->createEntityTranslation($referenced_entity, $d_lang, $t_lang, $target_type);

      if ($enable_debug) {
        $logger->debug('Entity reference field @field: Created new translation for @type entity @id', [
          '@field' => $field->getName(),
          '@type' => $target_type,
          '@id' => $item['target_id'],
        ]);
      }

    } catch (\Exception $e) {
      $logger->error('Error translating entity reference field @field: @error', [
        '@field' => $field->getName(),
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Creates a translation for a referenced entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to translate.
   * @param string $d_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   * @param string $entity_type
   *   The entity type (taxonomy_term, node, etc.).
   */
  private function createEntityTranslation(ContentEntityInterface $entity, $d_lang, $t_lang, $entity_type) {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    try {
      // Initialize translation data
      $translation_data = [];

      // Handle different entity types
      switch ($entity_type) {
        case 'taxonomy_term':
          if ($entity->hasField('name')) {
            $original_name = $entity->get('name')->value;
            $translated_name = $this->translate($original_name, $d_lang, $t_lang);
            $translation_data['name'] = $translated_name;
          }
          if ($entity->hasField('description')) {
            $original_description = $entity->get('description')->value;
            if (!empty($original_description)) {
              $translated_description = $this->translate($original_description, $d_lang, $t_lang);
              $translation_data['description'] = [
                'value' => $translated_description,
                'format' => $entity->get('description')->format,
              ];
            }
          }
          break;

        case 'node':
          if ($entity->hasField('title')) {
            $original_title = $entity->get('title')->value;
            $translated_title = $this->translate($original_title, $d_lang, $t_lang);
            $translation_data['title'] = $translated_title;
          }
          // Add other commonly translated node fields
          $translatable_fields = ['body', 'field_summary', 'field_description'];
          foreach ($translatable_fields as $field_name) {
            if ($entity->hasField($field_name) && $entity->get($field_name)->getFieldDefinition()->isTranslatable()) {
              $field_value = $entity->get($field_name)->value;
              if (!empty($field_value)) {
                $translated_value = $this->translate($field_value, $d_lang, $t_lang);
                $translation_data[$field_name] = [
                  'value' => $translated_value,
                  'format' => $entity->get($field_name)->format ?? 'basic_html',
                ];
              }
            }
          }
          break;

        case 'media':
          if ($entity->hasField('name')) {
            $original_name = $entity->get('name')->value;
            $translated_name = $this->translate($original_name, $d_lang, $t_lang);
            $translation_data['name'] = $translated_name;
          }
          break;
      }

      // Create the translation
      if (!empty($translation_data)) {
        $translated_entity = $entity->addTranslation($t_lang, $translation_data);
        $translated_entity->save();

        if ($enable_debug) {
          $logger->info('Created translation for @type entity @id in language @lang', [
            '@type' => $entity_type,
            '@id' => $entity->id(),
            '@lang' => $t_lang,
          ]);
        }
      }

    } catch (\Exception $e) {
      $logger->error('Error creating entity translation for @type entity @id: @error', [
        '@type' => $entity_type,
        '@id' => $entity->id(),
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Batch callback: Translates paragraph fields.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   The field name.
   * @param string $d_lang
   *   The default language.
   * @param string $t_lang
   *   The target language.
   * @param array $excludedFields
   *   Array of field names to exclude.
   * @param array $context
   *   The batch context.
   */
  public static function batchTranslateParagraphs($entity_id, $entity_type, $field_name, $d_lang, $t_lang, $excludedFields, $action, &$context) {
    $auto_translate_service = \Drupal::service('auto_translation.utility');
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);

    if (!$entity instanceof ContentEntityInterface) {
      $auto_translate_service->getLogger('auto_translation')->error($auto_translate_service->t('Translation error: Entity is not a content entity.'));
      return;
    }
    // Ensure translation exists.
    $translated_entity = $entity->getTranslation($t_lang);
    // Process each paragraph item in the field.
    $paragraphItems = $translated_entity->get($field_name);
    foreach ($paragraphItems as $paragraphItem) {
      if ($paragraphItem->entity instanceof \Drupal\paragraphs\ParagraphInterface) {
        // Process the translation of the paragraph recursively.
        self::batchProcessParagraphTranslation($paragraphItem->entity, $d_lang, $t_lang, $excludedFields);
      }
    }
    $context['message'] = $auto_translate_service->t('Translated paragraphs for field @field', ['@field' => $field_name]);
  }

  /**
   * Batch helper callback: Processes translation for a paragraph entity recursively.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraphEntity
   *   The paragraph entity.
   * @param string $d_lang
   *   The default language.
   * @param string $t_lang
   *   The target language.
   * @param array $excludedFields
   *   Array of field names to exclude.
   */
  public static function batchProcessParagraphTranslation($paragraphEntity, $d_lang, $t_lang, $excludedFields) {
    if (!$paragraphEntity instanceof \Drupal\paragraphs\ParagraphInterface) {
      return;
    }
    // Create or retrieve the translation.
    if (!$paragraphEntity->hasTranslation($t_lang)) {
      $translatedParagraph = $paragraphEntity->addTranslation($t_lang, []);
    }
    else {
      $translatedParagraph = $paragraphEntity->getTranslation($t_lang);
    }

    $auto_translate_service = \Drupal::service('auto_translation.utility');
    // Iterate over each field in the paragraph.
    foreach ($paragraphEntity->getFields() as $field_name => $field) {
      // Check if field is in user-defined excluded fields
      $isUserExcluded = isset($excludedFields['user_settings']) &&
                       is_array($excludedFields['user_settings']) &&
                       in_array($field_name, $excludedFields['user_settings']);

      if (in_array($field_name, $excludedFields) || $isUserExcluded || $field_name == 'default_langcode') {
        continue;
      }
      if ($auto_translate_service->isStaticParagraphReference($field)) {
        // Process nested paragraphs recursively.
        $nestedParagraphs = $paragraphEntity->get($field_name);
        foreach ($nestedParagraphs as $nestedParagraph) {
          if ($nestedParagraph->entity instanceof \Drupal\paragraphs\ParagraphInterface) {
            $auto_translate_service->batchProcessParagraphTranslation($nestedParagraph->entity, $d_lang, $t_lang, $excludedFields);
          }
        }
      }
      else {
        // Translate the field.
        if ($field->getFieldDefinition()->isTranslatable()) {

          // Retrieve the field values.
          $field_values = $paragraphEntity->get($field_name)->getValue();

          // Iterate over each item to translate it.
          foreach ($field_values as &$item) {
            // Check exclusion again for safety
            $isUserExcluded = isset($excludedFields['user_settings']) &&
                             is_array($excludedFields['user_settings']) &&
                             in_array($field_name, $excludedFields['user_settings']);

            if (!$field->getFieldDefinition()->isTranslatable() ||
                in_array($field_name, $excludedFields) ||
                $isUserExcluded ||
                str_starts_with($field->getFieldDefinition()->getType(), 'list_')) {
              continue;
            }

            switch ($field->getFieldDefinition()->getType()) {
              case 'link':
                if (!empty($item['title']) && is_string($item['title'])) {
                  $item['title'] = $auto_translate_service->translate($item['title'], $d_lang, $t_lang);
                }
                $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['title']);
                break;

              case 'image':
                if (!empty($item['alt']) && is_string($item['alt'])) {
                  $item['alt'] = $auto_translate_service->translate($item['alt'], $d_lang, $t_lang);
                  $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['alt']);
                }
                if (!empty($item['title']) && is_string($item['title'])) {
                  $item['title'] = $auto_translate_service->translate($item['title'], $d_lang, $t_lang);
                  $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['title']);
                }
                break;

              case 'file':
                if (!empty($item['description']) && is_string($item['description'])) {
                  $item['description'] = $auto_translate_service->translate($item['description'], $d_lang, $t_lang);
                  $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item['description']);
                }
                break;

              case 'entity_reference':
                // Handle entity reference fields in paragraphs (taxonomy terms, nodes, etc.)
                $auto_translate_service->translateEntityReferenceField($item, $field, $d_lang, $t_lang);
                break;

              case 'entity_reference_revisions':
                // Handle entity reference revisions (excluding nested paragraphs which are handled separately)
                if ($field->getFieldDefinition()->getSetting('target_type') !== 'paragraph') {
                  $auto_translate_service->translateEntityReferenceField($item, $field, $d_lang, $t_lang);
                }
                break;

              default:
                foreach ($item as $key => $value) {
                  if (is_string($value) && !empty($value) && ($key === 'value' || $key === '#value' || $key === 'summary' || $key === 'summary_override' || $key === '#default_value')) {
                    $item[$key] = $auto_translate_service->translate($value, $d_lang, $t_lang);
                    $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Translating paragraph field:') . ' ' . $field_name . ' ' . $auto_translate_service->t('with value:') . ' ' . $item[$key]);
                  }
                  else {
                    $item[$key] = $value;
                  }
                }
                break;
            }
          }
          // Set the translated value in the translated paragraph.
          $translatedParagraph->set($field_name, $field_values);
          $context['message'] = $auto_translate_service->t('Translated paragraph field @field', ['@field' => $field_name]);
        }
      }
    }
    // Finalize the translation.
    $translatedParagraph->save();
  }

  /**
   * Batch callback: Finalizes the entity translation by setting moderation,
   * status, revision, and saving the entity.
   *
   * @param int $entity_id
   *   The entity ID.
   * @param string $entity_type
   *   The entity type.
   * @param string $t_lang
   *   The target language.
   * @param string $action
   *   The action identifier.
   * @param array $context
   *   The batch context.
   */
  public static function batchFinalizeEntity($entity_id, $entity_type, $t_lang, $action, $chunk, &$context) {
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    $auto_translate_service = \Drupal::service('auto_translation.utility');

    if (!$entity instanceof ContentEntityInterface) {
      $auto_translate_service->getLogger('auto_translation')->error($auto_translate_service->t('Translation error: Entity is not a content entity.'));
      return;
    }

    $translated_entity = $entity->getTranslation($t_lang);

    if ($action && $action === "publish_action") {
      if ($translated_entity->hasField('moderation_state')) {
        $translated_entity->moderation_state->value = 'published';
        $translated_entity->moderation_state->langcode = $t_lang;
      }
      $translated_entity->set('status', 1);
    }
    else {
      if ($translated_entity->hasField('moderation_state')) {
        $translated_entity->moderation_state->value = 'draft';
        $translated_entity->moderation_state->langcode = $t_lang;
      }
      $translated_entity->set('status', 0);
    }
    $translated_entity->setNewRevision(TRUE);
    $translated_entity->setRevisionTranslationAffectedEnforced(TRUE);
    if ($translated_entity instanceof \Drupal\Core\Entity\RevisionLogInterface) {
      $translated_entity->setRevisionLogMessage($auto_translate_service->t('Translation added by Auto Translation module'));
    }
    $translated_entity->save();
    if ($entity_type == 'node') {
      $title = $translated_entity->get('title')->value;
    }
    elseif ($entity_type == 'media') {
      $title = $translated_entity->get('name')->value;
    }
    else {
      $title = '';
    }
    $auto_translate_service->getLogger('auto_translation')->debug($auto_translate_service->t('Saved translated entity:') . ' ' . $translated_entity->id() . ' ' . $auto_translate_service->t('with title:') . ' ' . $title);
    $context['message'] = $auto_translate_service->t('Finalized translation for entity @id', ['@id' => $entity_id]);
    $context['results']['entity_type'] = $entity_type;
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   TRUE if the batch finished successfully, FALSE otherwise.
   * @param array $results
   *   An array of results from the batch operations.
   * @param array $operations
   *   The operations that were run.
   */
  public static function batchFinishedCallback($success, $results, $operations) {
    if ($success) {
      if (!empty($results)) {
        $languages = \Drupal::service('language_manager')->getLanguages();
        \Drupal::messenger()->addMessage(t('Entity translation completed'));
        foreach ($results as $key => $result) {
          if (in_array($key, array_keys($languages))) {
            \Drupal::messenger()->addMessage($result);
          }
        }
      }
      else {
        \Drupal::messenger()->addMessage(t('No entities were translated.'));
      }
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred during entity translation.'));
    }
    if ($results['entity_type'] == 'node') {
      $route = 'system.admin_content';
    }
    elseif ($results['entity_type'] == 'media') {
      $route = 'entity.media.collection';
    }
    else {
      $route = 'system.admin_content';
    }
    return new RedirectResponse(Url::fromRoute($route)->toString());
  }

  /**
   * Helper static function to determine if a field is a paragraph reference.
   *
   * This version is static and can be used in batch callbacks.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field.
   *
   * @return bool
   *   TRUE if the field is a paragraph reference, FALSE otherwise.
   */
  public static function isStaticParagraphReference($field) {
    // Adjust the condition based on your implementation.
    return ($field->getFieldDefinition()->getType() == 'entity_reference_revisions' ||
      ($field->getFieldDefinition()->getType() == 'entity_reference' && $field->getFieldDefinition()->getSetting('target_type') == 'paragraph'));
  }

  /**
   * Handles translation for simple fields, as well as 'link' and 'image' fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity or paragraph.
   * @param array &$widget
   *   The form widget array for the field.
   * @param string $field_name
   *   The name of the field to translate.
   * @param string $field_type
   *   The type of the field.
   * @param string $d_lang
   *   The default language code.
   * @param string $t_lang
   *   The target language code.
   */
  private function translateField($entity, &$widget, $field_name, $field_type, $d_lang, $t_lang) {
    foreach ($widget as $key => &$sub_widget) {
      if (is_numeric($key) && is_array($sub_widget)) {
        // List of potentially translatable keys.
        $translatable_keys = ['value', '#default_value', 'title'];

        // Set format if available.
        if (isset($sub_widget['#format'])) {
          $sub_widget['value']['#format'] = $entity->get($field_name)->format;
        }
        if (isset($sub_widget['#text_format'])) {
          $sub_widget['value']['#text_format'] = $entity->get($field_name)->format;
        }

        // Translate all the string fields.
        foreach ($translatable_keys as $field) {
          if (isset($sub_widget[$field])) {
            if (is_array($sub_widget[$field]) && isset($sub_widget[$field]['#default_value']) && is_string($sub_widget[$field]['#default_value']) && !empty($sub_widget[$field]['#default_value'])) {
              $sub_widget[$field]['#default_value'] = $this->translate($sub_widget[$field]['#default_value'], $d_lang, $t_lang);
            }
            elseif (is_string($sub_widget[$field]) && !empty($sub_widget[$field])) {
              $sub_widget[$field] = $this->translate($sub_widget[$field], $d_lang, $t_lang);
            }
          }
        }
      }
    }

    // Translate field media alt text, title text.
    if (isset($widget[0]["#default_value"]["alt"]) && !empty($widget[0]["#default_value"]["alt"])) {
      $widget[0]["#default_value"]["alt"] = $this->translate($widget[0]["#default_value"]["alt"], $d_lang, $t_lang);
    }
    if (isset($widget[0]["#default_value"]["title"]) && !empty($widget[0]["#default_value"]["title"])) {
      $widget[0]["#default_value"]["title"] = $this->translate($widget[0]["#default_value"]["title"], $d_lang, $t_lang);
    }
  }

  /**
   * Checks if a field is a reference to a paragraph.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field definition.
   *
   * @return bool
   *   TRUE if the field points to a paragraph, FALSE otherwise.
   */
  private function isParagraphReference($field) {
    return ($field->getSetting('target_type') === 'paragraph');
  }

  /**
   * Handles the translation of paragraph fields recursively.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The main entity.
   * @param array &$form
   *   The form array.
   * @param string $field_name
   *   The name of the field containing the paragraphs.
   * @param string $d_lang
   *   The default language code.
   * @param string $t_lang
   *   The target language code.
   * @param array $excludedFields
   *   Array of field names to exclude from translation.
   */
  private function translateParagraphs($entity, &$form, $field_name, $d_lang, $t_lang, $excludedFields) {
    $paragraphItems = $entity->get($field_name);
    $layout = NULL;
    foreach ($paragraphItems as $index => $paragraphItem) {
      if ($paragraphItem->entity instanceof \Drupal\paragraphs\ParagraphInterface) {
        if (isset($form[$field_name]['widget'][$index]['subform'])) {
          // Standard paragraphs widget: translate via form subform.
          $this->processParagraphTranslation($paragraphItem->entity, $form[$field_name]['widget'][$index]['subform'], $d_lang, $t_lang, $excludedFields);
        }
        elseif ($layout = $form[$field_name]['widget']['layout_paragraphs_builder']['#layout_paragraphs_layout'] ?? NULL) {
          // Layout Paragraphs widget: translate via entity (no subform available).
          self::batchProcessParagraphTranslation($paragraphItem->entity, $d_lang, $t_lang, $excludedFields);
        }
      }
    }
    if (!empty($layout)) {
      \Drupal::service('layout_paragraphs.tempstore_repository')->set($layout);
    }
  }

  /**
   * Processes the translation of a paragraph entity recursively.
   *
   * For each translatable field of the paragraph (and nested paragraphs),
   * the translation is performed, and at the end, the translated entity is saved.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraphEntity
   *   The paragraph entity.
   * @param array &$form
   *   The subform array related to the paragraph.
   * @param string $d_lang
   *   The default language code.
   * @param string $t_lang
   *   The target language code.
   * @param array $excludedFields
   *   Array of field names to exclude.
   */
  private function processParagraphTranslation($paragraphEntity, &$form, $d_lang, $t_lang, $excludedFields) {
    if (!$paragraphEntity instanceof \Drupal\paragraphs\ParagraphInterface) {
      return;
    }

    // Iterate over each field of the paragraph.
    foreach ($paragraphEntity->getFields() as $field_name => $field) {
      // If the field is translatable and not among the excluded ones, proceed.
      if ($field->getFieldDefinition()->isTranslatable() && !in_array($field_name, $excludedFields)) {
        if (isset($form[$field_name]['widget'])) {
          $this->translateField($paragraphEntity, $form[$field_name]['widget'], $field_name, $field->getFieldDefinition()->getType(), $d_lang, $t_lang);
        }
      }
      // If the field is a reference to paragraphs, handle it recursively.
      if ($this->isParagraphReference($field)) {
        $nestedItems = $paragraphEntity->get($field_name);
        foreach ($nestedItems as $idx => $nestedItem) {
          if ($nestedItem->entity instanceof \Drupal\paragraphs\ParagraphInterface) {
            if (isset($form[$field_name]['widget'][$idx]['subform'])) {
              $this->processParagraphTranslation($nestedItem->entity, $form[$field_name]['widget'][$idx]['subform'], $d_lang, $t_lang, $excludedFields);
            }
          }
        }
      }
    }
  }

  /**
   * Custom get string between function.
   */
  public function getStringBetween($string, $start, $end) {
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) {
      return '';
    }
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
  }

  /**
   * Retrieves the container.
   *
   * @return mixed
   *   The container.
   */
  public static function getContainer() {
    return \Drupal::getContainer();
  }

  /**
   * Retrieves the configuration settings.
   *
   * @return object
   *   The configuration settings.
   */
  public static function config() {
    return static::getContainer()
      ->get('config.factory')
      ->get('auto_translation.settings');
  }

  /**
   * Retrieves the source and target languages for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The translated entity.
   *
   * @return array
   *   An array with 'original' and 'translated' containing the respective languages.
   */
  private function getTranslationLanguages(ContentEntityInterface $entity) {
    $language_manager = \Drupal::service('language_manager');
    $route_match = \Drupal::routeMatch();

    // Try to get the languages directly from the URL.
    $original_language = $route_match->getParameter('source') ?? NULL;
    $translated_language = $route_match->getParameter('target') ?? NULL;

    // If we find the languages in the URL, use them directly and in the correct order.
    if (!empty($original_language) && !empty($translated_language)) {
      return [
      // The first language from the URL.
        'original' => (string) $original_language->getId(),
      // The second language from the URL.
        'translated' => (string) $translated_language->getId(),
      ];
    }

    // If the entity is not translatable, use only the current language.
    if (!$entity->getEntityType()->isTranslatable()) {
      return [
        'original' => $entity->language()->getId(),
        'translated' => $language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId(),
      ];
    }

    // Find the original language of the entity.
    $default_language = $entity->getUntranslated()->language()->getId();

    // Current language = the language we are translating into.
    $current_language = $language_manager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    return [
    // The base language of the entity.
      'original' => $default_language,
    // The language we are translating into.
      'translated' => $current_language,
    ];
  }

  /**
   * Retrieves the specified form.
   *
   * @param object $messages
   *   The json of the message to retrieve.
   *
   * @return mixed
   *   The service object if found, null otherwise.
   */
  public static function getMessages($messages) {
    $auto_translation_service = \Drupal::service('auto_translation.utility');
    return static::getContainer()
      ->get('messenger')->addMessage($auto_translation_service->t('Auto translation error: @error', [
        '@error' => Markup::create(htmlentities(json_encode($messages))),
      ]), MessengerInterface::TYPE_ERROR);
  }

  /**
   * Returns the path of the module.
   *
   * @return string
   *   The path of the module.
   */
  public static function getModulePath() {
    return static::getContainer()
      ->get('extension.list.module')
      ->getPath('auto_translation');
  }

  /**
   * Retrieves the specified module by name.
   *
   * @param string $module_name
   *   The name of the module to retrieve.
   *
   * @return mixed|null
   *   The module object if found, null otherwise.
   */
  public static function getModule($module_name) {
    return static::getContainer()
      ->get('extension.list.module')
      ->get($module_name);
  }

  /**
   * Encrypts the API key using AES-256-CBC.
   *
   * @param string $plainApiKey
   *   The API key in plaintext.
   *
   * @return string
   *   The encrypted API key (base64-encoded IV concatenated with the ciphertext).
   */
  public function encryptApiKey(?string $plainApiKey): string {
    if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_encrypt') || empty($plainApiKey)) {
      // The OpenSSL extension is not available.
      if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_encrypt')) {
        $this->getLogger('auto_translation')->warning('OpenSSL extension is not available.');
        $this->messenger->addWarning($this->t('The OpenSSL extension is not available. Please enable it to re-encrypt the API key.'));
      }
      return $plainApiKey;
    }
    // Define a "master key". You can define it in settings.php for enhanced security.
    // For example, use DRUPAL_HASH_SALT or a custom constant.
    $secret = $this->configFactory->getEditable('auto_translation.settings')->get('custom_secret');
    if (empty($secret)) {
      $config = $this->configFactory->getEditable('auto_translation.settings');
      // Generate a 32-character random string.
      $secret = bin2hex(random_bytes(16));
      $config->set('custom_secret', $secret)->save();
    }
    else {
      $secret = $this->configFactory->getEditable('auto_translation.settings')->get('custom_secret');
    }
    // Derive a 256-bit key (32 bytes) from the secret.
    $key = substr(hash('sha256', $secret, TRUE), 0, 32);

    // Get the initialization vector (IV) length for AES-256-CBC.
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    // Generate a cryptographically secure IV.
    $iv = random_bytes($ivLength);

    // Encrypt the API key using AES-256-CBC. Using OPENSSL_RAW_DATA returns raw binary data.
    $ciphertext = openssl_encrypt($plainApiKey, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    // To decrypt, you must also save the IV.
    // Here we concatenate the IV with the ciphertext and then base64-encode the result.
    return base64_encode($iv . $ciphertext);
  }

  /**
   * Decrypts an API key previously encrypted.
   *
   * @param string $encryptedApiKey
   *   The encrypted API key (base64-encoded string containing IV + ciphertext).
   *
   * @return string|false
   *   The decrypted API key in plaintext or FALSE if decryption fails.
   */
  public function decryptApiKey(?string $encryptedApiKey): string {
    if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_decrypt') || empty($encryptedApiKey)) {
      // The OpenSSL extension is not available.
      if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_decrypt')) {
        $this->getLogger('auto_translation')->warning('OpenSSL extension is not available.');
        $this->messenger->addWarning($this->t('The OpenSSL extension is not available. Please enable it to re-encrypt the API key.'));
      }
      return $encryptedApiKey;
    }
    $secret = $this->configFactory->getEditable('auto_translation.settings')->get('custom_secret');
    if (empty($secret)) {
      $config = $this->configFactory->getEditable('auto_translation.settings');
      // Generate a 32-character random string.
      $secret = bin2hex(random_bytes(16));
      $config->set('custom_secret', $secret)->save();
    }
    else {
      $secret = $this->configFactory->getEditable('auto_translation.settings')->get('custom_secret');
    }
    $key = substr(hash('sha256', $secret, TRUE), 0, 32);

    // Decode the base64-encoded string and split the IV from the ciphertext.
    $data = base64_decode($encryptedApiKey);
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($data, 0, $ivLength);
    $ciphertext = substr($data, $ivLength);

    return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
  }

  /**
   * Retrieves the user permissions.
   *
   * @return bool
   *   TRUE if the user has the permission, FALSE otherwise.
   */
  public function hasPermission() {
    $permission = 'auto translation translate content';
    $current_user = \Drupal::currentUser();
    $permissions = \Drupal::service('user.permissions')->getPermissions();
    if (isset($permissions[$permission])) {
      return $current_user->hasPermission($permission);
    }
    return FALSE;
  }

  /**
   * Translates HTML content while preserving structure and whitespace.
   *
   * Uses Drupal's Html utility class and implements proper caching and error handling.
   *
   * @param string $html
   *   The HTML content to translate.
   * @param string $s_lang
   *   Source language code.
   * @param string $t_lang
   *   Target language code.
   *
   * @return string
   *   The translated HTML content.
   */
  private function translateHtmlContent($html, $s_lang, $t_lang, $provider = 'google') {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('HTML Translation - Processing HTML content securely');
    }

    // Always use the secure text extraction method for all providers
    return $this->translateHtmlWithSafeTextExtraction($html, $s_lang, $t_lang, $provider);
  }

  /**
   * Direct HTML translation method for DeepL free API.
   *
   * This method translates HTML content directly without breaking it into segments,
   * which works better with DeepL free API that can handle HTML but has issues with
   * extracted text segments.
   */
  private function translateHtmlDirectly($html, $s_lang, $t_lang, $provider) {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('HTML Translation - Using direct method for @provider', ['@provider' => $provider]);
    }

    // Check cache first
    $cache_key = 'auto_translation:html_direct:' . md5($html . $s_lang . $t_lang . $provider);
    $cached = $this->cacheBackend->get($cache_key);
    if ($cached && !empty($cached->data)) {
      if ($enable_debug) {
        $logger->debug('Cache hit for direct HTML translation from @s_lang to @t_lang', [
          '@s_lang' => $s_lang,
          '@t_lang' => $t_lang,
        ]);
      }
      return $cached->data;
    }

    try {
      // Translate the entire HTML content directly
      $translated = $this->callProviderTranslationApi($html, $s_lang, $t_lang, $provider);

      if ($translated && !empty(trim($translated))) {
        // Cache the result
        $this->cacheBackend->set($cache_key, $translated, time() + 3600, ['auto_translation']);

        if ($enable_debug) {
          $logger->info('HTML Translation - Direct method completed successfully');
        }

        return $translated;
      } else {
        $logger->warning('Direct HTML translation failed or returned empty for provider: @provider', [
          '@provider' => $provider,
        ]);
        return $html; // Return original on failure
      }
    } catch (\Exception $e) {
      $logger->error('Error in direct HTML translation: @error', ['@error' => $e->getMessage()]);
      return $html; // Return original on error
    }
  }

  /**
   * Simple HTML translation method for Google Browser API and DeepL free API.
   *
   * This method uses a more conservative approach that works better with
   * APIs that have limitations with complex HTML processing.
   */
  private function translateHtmlWithSimpleMethod($html, $s_lang, $t_lang, $provider) {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('HTML Translation - Using simple method for @provider', ['@provider' => $provider]);
    }

    // Use the same pattern as the advanced method but with simpler processing
    $pattern = '/(<[^>]*>)|([^<]+)/';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

    $text_segments = [];
    $segment_mapping = [];

    foreach ($matches as $index => $match) {
      if (isset($match[2]) && !empty(trim($match[2]))) {
        // This is text content, not HTML tag
        $text = $match[2];
        $trimmed_text = trim($text);

        // Skip if the text contains only whitespace, numbers, punctuation, or symbols
        if (empty($trimmed_text) || strlen($trimmed_text) <= 1 ||
            preg_match('/^[\d\s\p{P}\p{S}]+$/u', $trimmed_text)) {
          continue;
        }

        $text_segments[] = [
          'text' => $trimmed_text,
          'original_full' => $text,
          'index' => $index
        ];
        $segment_mapping[$index] = count($text_segments) - 1;
      }
    }

    if (empty($text_segments)) {
      if ($enable_debug) {
        $logger->debug('No translatable text segments found in HTML');
      }
      return $html;
    }

    if ($enable_debug) {
      $logger->info('HTML Translation - Found @count text segments to translate', [
        '@count' => count($text_segments),
      ]);
    }

    // Translate each text segment
    $translated_segments = [];
    foreach ($text_segments as $segment) {
      $cache_key_segment = 'auto_translation:text:' . md5($segment['text'] . $s_lang . $t_lang . $provider);

      // Check cache first
      $cached_segment = $this->cacheBackend->get($cache_key_segment);
      if ($cached_segment && !empty($cached_segment->data)) {
        $translated_segments[] = $cached_segment->data;
        continue;
      }

      try {
        $translated = $this->callProviderTranslationApi($segment['text'], $s_lang, $t_lang, $provider);
        if ($translated && !empty(trim($translated))) {
          $translated_segments[] = $translated;
          // Cache the translation
          $this->cacheBackend->set($cache_key_segment, $translated, time() + 86400, ['auto_translation']);

          if ($enable_debug) {
            $logger->debug('HTML Translation - Translated: "@original" -> "@translated"', [
              '@original' => substr($segment['text'], 0, 30),
              '@translated' => substr($translated, 0, 30),
            ]);
          }
        } else {
          $translated_segments[] = $segment['text']; // Fallback to original
          if ($enable_debug) {
            $logger->warning('Translation failed or empty for: @text', ['@text' => $segment['text']]);
          }
        }
      } catch (\Exception $e) {
        $logger->error('Error translating segment: @error', ['@error' => $e->getMessage()]);
        $translated_segments[] = $segment['text'];
      }
    }

    // Reconstruct the HTML with translated segments
    $result = '';
    $segment_counter = 0;

    foreach ($matches as $index => $match) {
      if (!empty($match[1])) {
        // HTML tag - keep as is
        $result .= $match[1];
      } elseif (isset($match[2])) {
        // Text content - replace with translation if available
        if (isset($segment_mapping[$index])) {
          $segment_idx = $segment_mapping[$index];
          $segment = $text_segments[$segment_idx];
          $translated = $translated_segments[$segment_idx];

          // Preserve original whitespace structure
          $original_full = $segment['original_full'];
          $leading_space = '';
          $trailing_space = '';

          // Extract leading and trailing whitespace from original
          if (preg_match('/^(\s*)/', $original_full, $leading_matches)) {
            $leading_space = $leading_matches[1];
          }
          if (preg_match('/(\s*)$/', $original_full, $trailing_matches)) {
            $trailing_space = $trailing_matches[1];
          }

          $result .= $leading_space . $translated . $trailing_space;
        } else {
          // Keep original text (probably whitespace or excluded content)
          $result .= $match[2];
        }
      }
    }

    // Cache the result
    $cache_key = 'auto_translation:html:' . md5($html . $s_lang . $t_lang . $provider);
    $this->cacheBackend->set($cache_key, $result, time() + 3600, ['auto_translation']);

    if ($enable_debug) {
      $logger->info('HTML Translation - Simple method completed successfully');
    }

    return $result;
  }

  /**
   * Specialized HTML translation method for Google Browser API.
   *
   * This method NEVER sends raw HTML to the API. Instead, it extracts text,
   * translates it safely, and reconstructs the HTML with proper formatting.
   */
  private function translateHtmlForGoogleBrowser($html, $s_lang, $t_lang, $provider = 'google') {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('HTML Translation - Using Google Browser safe text extraction method');
    }

    // NEVER use direct HTML translation - always extract text first
    return $this->translateHtmlWithSafeTextExtraction($html, $s_lang, $t_lang, $provider);
  }

  /**
   * Safe HTML translation method that NEVER sends HTML to translation APIs.
   *
   * This method extracts text content, preserves HTML structure, translates
   * only the text, and reconstructs HTML with proper capitalization and spacing.
   */
  private function translateHtmlWithSafeTextExtraction($html, $s_lang, $t_lang, $provider) {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('HTML Translation - Using safe text extraction method');
    }

    // Parse HTML and extract text segments with their context
    $pattern = '/(<[^>]*>)|([^<]+)/';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

    $text_segments = [];
    $segment_mapping = [];

    // Extract translatable text segments
    foreach ($matches as $index => $match) {
      if (isset($match[2]) && !empty(trim($match[2]))) {
        $text = $match[2];
        $trimmed_text = trim($text);

        // Skip if empty or contains no letters
        if (empty($trimmed_text) || !preg_match('/[A-Za-z]/', $trimmed_text)) {
          continue;
        }

        // Analyze the text context for proper reconstruction
        $context = $this->analyzeTextContext($text, $matches, $index);

        $text_segments[] = [
          'text' => $trimmed_text,
          'original' => $text,
          'context' => $context,
          'index' => $index
        ];
        $segment_mapping[$index] = count($text_segments) - 1;

        if ($enable_debug) {
          $logger->debug('Safe extraction - Text: "@text"', [
            '@text' => substr($trimmed_text, 0, 50)
          ]);
        }
      }
    }

    if (empty($text_segments)) {
      return $html;
    }

    // Translate each text segment individually (NEVER HTML)
    $translated_segments = [];
    foreach ($text_segments as $segment) {
      $cache_key = 'auto_translation:safe_text:' . md5($segment['text'] . $s_lang . $t_lang);

      $cached = $this->cacheBackend->get($cache_key);
      if ($cached && !empty($cached->data)) {
        $translated_segments[] = $cached->data;
        continue;
      }

      try {
        // Translate ONLY the plain text (never HTML)
        $translated = $this->callProviderTranslationApi($segment['text'], $s_lang, $t_lang, $provider);
        if ($translated && !empty(trim($translated))) {
          // Apply capitalization fixes
          $translated = $this->fixCapitalization($translated, $segment['text'], $segment['context']);
          $translated_segments[] = $translated;

          // Cache the result
          $this->cacheBackend->set($cache_key, $translated, time() + 86400, ['auto_translation']);

          if ($enable_debug) {
            $logger->debug('Safe translation: "@original" -> "@translated"', [
              '@original' => substr($segment['text'], 0, 30),
              '@translated' => substr($translated, 0, 30)
            ]);
          }
        } else {
          $translated_segments[] = $segment['text'];
        }
      } catch (\Exception $e) {
        $logger->error('Error in safe text translation: @error', ['@error' => $e->getMessage()]);
        $translated_segments[] = $segment['text'];
      }
    }

    // Reconstruct HTML with translated text and proper formatting
    $result = '';
    foreach ($matches as $index => $match) {
      if (!empty($match[1])) {
        // HTML tag - keep as is
        $result .= $match[1];
      } elseif (isset($match[2])) {
        // Text content - replace with translation if available
        if (isset($segment_mapping[$index])) {
          $segment_idx = $segment_mapping[$index];
          $segment = $text_segments[$segment_idx];
          $translated = $translated_segments[$segment_idx];

          // Reconstruct with proper spacing and formatting
          $final_text = $this->reconstructTextWithProperFormatting(
            $translated,
            $segment['original'],
            $segment['context']
          );

          $result .= $final_text;
        } else {
          // Keep original (probably whitespace or non-translatable)
          $result .= $match[2];
        }
      }
    }

    // Apply final formatting fixes
    $result = $this->applySafeFormattingFixes($result, $html);

    // Cache the final result
    $cache_key = 'auto_translation:safe_html:' . md5($html . $s_lang . $t_lang);
    $this->cacheBackend->set($cache_key, $result, time() + 3600, ['auto_translation']);

    if ($enable_debug) {
      $logger->info('Safe HTML translation completed successfully');
    }

    return $result;
  }

  /**
   * Analyzes text context for proper reconstruction.
   */
  private function analyzeTextContext($text, $all_matches, $current_index) {
    $context = [
      'leading_space' => '',
      'trailing_space' => '',
      'starts_sentence' => false,
      'ends_sentence' => false,
      'after_inline_tag' => false,
      'before_inline_tag' => false
    ];

    // Extract whitespace
    if (preg_match('/^(\s+)/', $text)) {
      $context['leading_space'] = preg_match('/^(\s+)/', $text, $matches) ? $matches[1] : '';
    }
    if (preg_match('/(\s+)$/', $text)) {
      $context['trailing_space'] = preg_match('/(\s+)$/', $text, $matches) ? $matches[1] : '';
    }

    // Check if starts with capital letter (likely sentence start)
    $trimmed = trim($text);
    $context['starts_sentence'] = !empty($trimmed) && preg_match('/^[A-Z]/', $trimmed);

    // Check if ends with sentence punctuation
    $context['ends_sentence'] = !empty($trimmed) && preg_match('/[.!?]\s*$/', $trimmed);

    // Check surrounding HTML tags
    if ($current_index > 0 && isset($all_matches[$current_index - 1][1])) {
      $prev_tag = $all_matches[$current_index - 1][1];
      $context['after_inline_tag'] = preg_match('/<\/(strong|em|b|i|span|a|code|small|sub|sup|mark|del|ins)>/i', $prev_tag);
    }

    if ($current_index < count($all_matches) - 1 && isset($all_matches[$current_index + 1][1])) {
      $next_tag = $all_matches[$current_index + 1][1];
      $context['before_inline_tag'] = preg_match('/<(strong|em|b|i|span|a|code|small|sub|sup|mark|del|ins)/i', $next_tag);
    }

    return $context;
  }

  /**
   * Fixes capitalization issues in translated text.
   */
  private function fixCapitalization($translated, $original, $context) {
    $trimmed_translated = trim($translated);

    // Don't over-capitalize - only fix obvious cases
    if ($context['starts_sentence'] && !empty($trimmed_translated)) {
      // Only capitalize first letter if it's not already capitalized
      if (!preg_match('/^[A-Z]/', $trimmed_translated)) {
        $trimmed_translated = ucfirst($trimmed_translated);
      }
    } else {
      // For non-sentence starts, ensure first letter is lowercase unless it's a proper noun
      // But be conservative - only change obvious over-capitalizations
      if (preg_match('/^[A-Z][a-z]/', $trimmed_translated) && !preg_match('/^[A-Z][a-z]+$/', $original)) {
        // Only lowercase if the original wasn't a single capitalized word
        $trimmed_translated = lcfirst($trimmed_translated);
      }
    }

    // Fix capitalization after punctuation within the text
    $trimmed_translated = preg_replace_callback('/([.!?]\s+)([a-z])/',
      function($matches) {
        return $matches[1] . strtoupper($matches[2]);
      },
      $trimmed_translated
    );

    return $trimmed_translated;
  }

  /**
   * Reconstructs text with proper formatting and spacing.
   */
  private function reconstructTextWithProperFormatting($translated, $original, $context) {
    $result = trim($translated);

    // Handle spacing more carefully for inline tags and punctuation
    $add_leading_space = false;
    $add_trailing_space = false;

    // Determine if we need leading space
    if (!empty($context['leading_space'])) {
      if (preg_match('/\n/', $context['leading_space'])) {
        // Preserve newlines
        $result = $context['leading_space'] . $result;
      } else {
        // For regular spaces, be smarter about when to add them
        $add_leading_space = true;
      }
    }

    // Determine if we need trailing space
    if (!empty($context['trailing_space'])) {
      if (preg_match('/\n/', $context['trailing_space'])) {
        // Preserve newlines
        $result = $result . $context['trailing_space'];
      } else {
        // For regular spaces, check context
        $add_trailing_space = true;
      }
    }

    // Smart spacing: add spaces around text that should have them
    if ($add_leading_space) {
      // Don't add space if the text starts with punctuation
      if (!preg_match('/^[,.:;!?]/', $result)) {
        $result = ' ' . $result;
      }
    }

    if ($add_trailing_space) {
      // Always add trailing space unless text ends with certain punctuation
      if (!preg_match('/[,.:;!?]$/', $result)) {
        $result = $result . ' ';
      } else {
        // If text ends with punctuation, add space after the punctuation
        $result = $result . ' ';
      }
    }

    return $result;
  }

  /**
   * Applies safe formatting fixes without breaking HTML structure.
   */
  private function applySafeFormattingFixes($html, $original_html) {
    // Enhanced safe formatting fixes that handle inline tags and punctuation
    $patterns = [
      // Fix spacing around punctuation (but preserve inside HTML tags)
      '/\s+([,.!?;:])/' => '$1',  // Remove space before punctuation

      // Fix spacing around inline HTML tags
      '/([,.!?;:])</' => '$1 <',  // Space between punctuation and closing tag
      '/([,.!?;:])<(\/?)/' => '$1 <$2',  // Space between punctuation and any tag
      '/>([A-Za-z])/' => '> $1',  // Space between closing tag and text
      '/([A-Za-z])</' => '$1 <',  // Space between text and opening tag (but be careful)

      // Fix missing spaces around inline tags more precisely
      '/<\/([^>]+)>([A-Za-z])/' => '</$1> $2',  // Space after closing inline tag
      '/([,.!?;:])<\/([^>]+)>/' => '$1</$2>',   // No space between punctuation and closing tag

      // Fix multiple spaces
      '/\s{2,}/' => ' ',

      // Remove empty list items (aggressive cleanup)
      '/<li[^>]*>\s*(&nbsp;|&#160;|&#xa0;)?\s*<\/li>\s*/' => '',
      '/<li[^>]*>\s*<\/li>\s*/' => '',
    ];

    foreach ($patterns as $pattern => $replacement) {
      $html = preg_replace($pattern, $replacement, $html);
    }

    // Preserve original list types
    if ($original_html) {
      if (strpos($original_html, '<ol>') !== false && strpos($html, '<ul>') !== false && strpos($html, '<ol>') === false) {
        $html = str_replace('<ul>', '<ol>', $html);
        $html = str_replace('</ul>', '</ol>', $html);
      }
      if (strpos($original_html, '<ul>') !== false && strpos($html, '<ol>') !== false && strpos($html, '<ul>') === false) {
        $html = str_replace('<ol>', '<ul>', $html);
        $html = str_replace('</ol>', '</ul>', $html);
      }
    }

    return $html;
  }

  /**
   * Applies post-processing fixes for common HTML formatting issues.
   *
   * @param string $html
   *   The translated HTML to fix.
   * @param string $original_html
   *   The original HTML before translation (optional, for structure preservation).
   *
   * @return string
   *   The fixed HTML.
   */
  private function applyHtmlFormattingFixes($html, $original_html = null) {
    // Fix multiple issues with inline HTML formatting, especially from Google Browser API
    $patterns = [
      // Remove extra spaces inside inline tags that Google Browser API adds
      // Fix opening tags: <strong> text -> <strong>text
      '/(<(strong|em|b|i|span|code|small|sub|sup|mark|del|ins)[^>]*>)\s+/' => '$1',

      // Fix closing tags: text </strong> -> text</strong>
      '/\s+(<\/(strong|em|b|i|span|code|small|sub|sup|mark|del|ins)>)/' => '$1',

      // Fix missing space after closing inline tags when followed directly by a letter
      '/(<\/(strong|em|b|i|span|code|small|sub|sup|mark|del|ins)>)([A-Za-z])/' => '$1 $3',

      // Fix missing space after punctuation following closing inline tags
      '/(<\/(strong|em|b|i|span|code|small|sub|sup|mark|del|ins)>)([,.:;!?])([A-Za-z])/' => '$1$3 $4',

      // Fix missing space after comma/period when followed by letter (but not in formatted blocks)
      '/([,.:;!?])([A-Za-z])(?![^<]*>)/' => '$1 $2',

      // Clean up multiple consecutive spaces (but preserve formatted content like <li> blocks)
      '/(?<!\n)\s{2,}(?!\n)/' => ' ',

      // AGGRESSIVE: Remove empty list items with just &nbsp; (multiple variations)
      '/<li[^>]*>\s*&nbsp;\s*<\/li>\s*/' => '',
      '/<li[^>]*>\s*&#160;\s*<\/li>\s*/' => '', // numeric entity
      '/<li[^>]*>\s*&#xa0;\s*<\/li>\s*/' => '', // hex entity

      // AGGRESSIVE: Remove completely empty list items (multiple variations)
      '/<li[^>]*>\s*<\/li>\s*/' => '',
      '/<li[^>]*><\/li>\s*/' => '',

      // AGGRESSIVE: Remove list items that contain only whitespace, tabs, or newlines
      '/<li[^>]*>[\s\t\n\r]*<\/li>\s*/' => '',

      // Fix space before punctuation that shouldn't be there (but preserve line formatting)
      '/(?<!\n)\s+([,.:;!?])/' => '$1',

      // Special fix for Google Browser API issue: remove space between comma and following word after strong
      '/(<\/(strong|em|b|i)>),\s+([a-zA-Z])/' => '$1, $3'
    ];

    // Apply patterns multiple times to catch nested issues
    $iterations = 0;
    $max_iterations = 3;

    do {
      $before = $html;
      foreach ($patterns as $pattern => $replacement) {
        $html = preg_replace($pattern, $replacement, $html);
      }
      $iterations++;
    } while ($html !== $before && $iterations < $max_iterations);

    // Additional fix: Preserve original list types if we have the original HTML
    if ($original_html) {
      // Fix Google Browser API changing <ol> to <ul> or vice versa
      if (strpos($original_html, '<ol>') !== false && strpos($html, '<ul>') !== false && strpos($html, '<ol>') === false) {
        $html = str_replace('<ul>', '<ol>', $html);
        $html = str_replace('</ul>', '</ol>', $html);
      }

      // Fix Google Browser API changing <ul> to <ol> or vice versa
      if (strpos($original_html, '<ul>') !== false && strpos($html, '<ol>') !== false && strpos($html, '<ul>') === false) {
        $html = str_replace('<ol>', '<ul>', $html);
        $html = str_replace('</ol>', '</ul>', $html);
      }
    }

    return $html;
  }

  /**
   * Advanced HTML translation method for providers with better HTML support.
   */
  private function translateHtmlWithAdvancedMethod($html, $s_lang, $t_lang, $provider) {
    $logger = $this->loggerFactory->get('auto_translation');
    $config = $this->configFactory->get('auto_translation.settings');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('HTML Translation - Using advanced method for @provider', ['@provider' => $provider]);
    }

    // Better regex pattern to capture HTML tags and text content separately
    $pattern = '/(<[^>]*>)|([^<]+)/';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

    $segments = [];
    $segment_mapping = [];

    foreach ($matches as $index => $match) {
      if (isset($match[2]) && !empty(trim($match[2]))) {
        // This is text content, not HTML tag
        $text = $match[2];
        $trimmed_text = trim($text);

        // Skip if the text contains only whitespace, numbers, punctuation, or symbols
        if (empty($trimmed_text) || strlen($trimmed_text) <= 1 ||
            preg_match('/^[\d\s\p{P}\p{S}]+$/u', $trimmed_text)) {
          continue;
        }

        // Improved spacing detection for inline elements
        $needs_leading_space = false;
        $needs_trailing_space = false;

        // Check if this text segment comes immediately after a closing inline HTML tag
        if ($index > 0 && isset($matches[$index - 1][1])) {
          $prev_tag = $matches[$index - 1][1];
          // Check if it's a closing inline tag and current text doesn't start with space
          if (preg_match('/<\/(strong|em|b|i|span|a|code|small|sub|sup|mark|del|ins)>/i', $prev_tag) && !preg_match('/^\s/', $text)) {
            $needs_leading_space = true;
            if ($enable_debug) {
              $logger->debug('HTML Translation - Adding leading space after inline tag: @tag', ['@tag' => $prev_tag]);
            }
          }
        }

        // Check if this text segment comes immediately before an opening inline HTML tag
        if ($index < count($matches) - 1 && isset($matches[$index + 1][1])) {
          $next_tag = $matches[$index + 1][1];
          // Check if it's an opening inline tag and current text doesn't end with space
          if (preg_match('/<(strong|em|b|i|span|a|code|small|sub|sup|mark|del|ins)(\s[^>]*)?>/i', $next_tag) && !preg_match('/\s$/', $text)) {
            $needs_trailing_space = true;
            if ($enable_debug) {
              $logger->debug('HTML Translation - Adding trailing space before inline tag: @tag', ['@tag' => $next_tag]);
            }
          }
        }

        $segments[] = [
          'text' => $trimmed_text,
          'original_full' => $text,
          'needs_leading_space' => $needs_leading_space,
          'needs_trailing_space' => $needs_trailing_space,
          'index' => $index
        ];
        $segment_mapping[$index] = count($segments) - 1;
      }
    }

    if (empty($segments)) {
      if ($enable_debug) {
        $logger->debug('No valid text segments found for translation');
      }
      return $html;
    }

    if ($enable_debug) {
      $logger->info('HTML Translation - Found @count text segments to translate', [
        '@count' => count($segments),
      ]);
    }

    // Translate all text segments
    $translated_segments = [];
    foreach ($segments as $segment) {
      $cache_key_segment = 'auto_translation:text:' . md5($segment['text'] . $s_lang . $t_lang . $provider);

      // Check cache for individual text segments
      $cached_segment = $this->cacheBackend->get($cache_key_segment);
      if ($cached_segment && !empty($cached_segment->data)) {
        $translated = $cached_segment->data;
      } else {
        try {
          $translated = $this->callProviderTranslationApi($segment['text'], $s_lang, $t_lang, $provider);
          if ($translated && !empty(trim($translated))) {
            // Cache the translation
            $this->cacheBackend->set($cache_key_segment, $translated, time() + 86400, ['auto_translation']);
            if ($enable_debug) {
              $logger->debug('HTML Translation - Successfully translated segment: "@original" -> "@translated"', [
                '@original' => substr($segment['text'], 0, 50),
                '@translated' => substr($translated, 0, 50),
              ]);
            }
          } else {
            $translated = $segment['text']; // Fallback to original
            $logger->warning('Translation failed or returned empty for text segment: @text', ['@text' => $segment['text']]);
          }
        } catch (\Exception $e) {
          $logger->error('Error translating text segment: @error', ['@error' => $e->getMessage()]);
          $translated = $segment['text']; // Fallback to original
        }
      }

      $translated_segments[] = $translated;
    }

    // Reconstruct the HTML with translated segments
    $result = '';
    foreach ($matches as $index => $match) {
      if (!empty($match[1])) {
        // HTML tag - keep as is
        $result .= $match[1];
      } elseif (isset($match[2])) {
        // Text content - replace with translation if available
        if (isset($segment_mapping[$index])) {
          $segment_idx = $segment_mapping[$index];
          $segment = $segments[$segment_idx];
          $translated = $translated_segments[$segment_idx];

          // Preserve original whitespace structure but with translated content
          $original_full = $segment['original_full'];
          $leading_space = '';
          $trailing_space = '';

          // Extract leading and trailing whitespace from original
          if (preg_match('/^(\s*)/', $original_full, $leading_matches)) {
            $leading_space = $leading_matches[1];
          }
          if (preg_match('/(\s*)$/', $original_full, $trailing_matches)) {
            $trailing_space = $trailing_matches[1];
          }

          // Add extra spaces if needed around inline elements
          if ($segment['needs_leading_space'] && empty($leading_space)) {
            $leading_space = ' ';
            if ($enable_debug) {
              $logger->debug('HTML Translation - Added leading space for inline element');
            }
          }

          if ($segment['needs_trailing_space'] && empty($trailing_space)) {
            $trailing_space = ' ';
            if ($enable_debug) {
              $logger->debug('HTML Translation - Added trailing space for inline element');
            }
          }

          $result .= $leading_space . $translated . $trailing_space;
        } else {
          // Keep original text (probably whitespace or excluded content)
          $result .= $match[2];
        }
      }
    }

    // Cache the result
    $cache_key = 'auto_translation:html:' . md5($html . $s_lang . $t_lang . $provider);
    $this->cacheBackend->set($cache_key, $result, time() + 3600, ['auto_translation']);

    if ($enable_debug) {
      $logger->info('HTML Translation - Advanced method completed successfully');
    }

    return $result;
  }

  /**
   * Extracts leading whitespace from a text string.
   *
   * @param string $text
   *   The text to analyze.
   *
   * @return string
   *   The leading whitespace characters.
   */
  private function extractLeadingWhitespace($text) {
    if (preg_match('/^(\s*)/', $text, $matches)) {
      return $matches[1];
    }
    return '';
  }

  /**
   * Extracts trailing whitespace from a text string.
   *
   * @param string $text
   *   The text to analyze.
   *
   * @return string
   *   The trailing whitespace characters.
   */
  private function extractTrailingWhitespace($text) {
    if (preg_match('/(\s*)$/', $text, $matches)) {
      return $matches[1];
    }
    return '';
  }

  /**
   * Translates an array of text segments efficiently.
   *
   * @param array $text_segments
   *   Array of text segments to translate.
   * @param string $s_lang
   *   Source language code.
   * @param string $t_lang
   *   Target language code.
   * @param string $provider
   *   The translation provider to use.
   *
   * @return array
   *   Array of translated segments.
   */
  private function translateTextSegments(array $text_segments, $s_lang, $t_lang, $provider = 'google') {
    $logger = $this->loggerFactory->get('auto_translation');
    $translated_segments = [];

    foreach ($text_segments as $index => $text_data) {
      $cache_key = 'auto_translation:text:' . md5($text_data['trimmed'] . $s_lang . $t_lang . $provider);

      // Check cache for individual text segments.
      $cached = $this->cacheBackend->get($cache_key);
      if ($cached && !empty($cached->data)) {
        $translated_segments[] = $text_data['leading_space'] . $cached->data . $text_data['trailing_space'];
        continue;
      }

      try {
        $translated = $this->callProviderTranslationApi($text_data['trimmed'], $s_lang, $t_lang, $provider);

        if ($translated) {
          // Cache the translation.
          $this->cacheBackend->set($cache_key, $translated, time() + 86400, ['auto_translation']);

          // Reconstruct with original whitespace.
          $reconstructed = $text_data['leading_space'] . $translated . $text_data['trailing_space'];
          $translated_segments[] = $reconstructed;
        } else {
          // Fallback to original text.
          $translated_segments[] = $text_data['original'];
          $logger->warning('Translation failed for text segment: @text', ['@text' => $text_data['trimmed']]);
        }
      } catch (\Exception $e) {
        $logger->error('Error translating text segment: @error', ['@error' => $e->getMessage()]);
        $translated_segments[] = $text_data['original'];
      }
    }

    return $translated_segments;
  }

  /**
   * Applies translated segments back to DOM nodes.
   *
   * @param array $node_mapping
   *   Array of DOM text nodes.
   * @param array $translated_segments
   *   Array of translated text segments.
   */
  private function applyTranslatedSegments(array $node_mapping, array $translated_segments) {
    $count = min(count($node_mapping), count($translated_segments));

    for ($i = 0; $i < $count; $i++) {
      if (isset($translated_segments[$i]) && $node_mapping[$i] instanceof \DOMText) {
        $node_mapping[$i]->nodeValue = $translated_segments[$i];
      }
    }
  }

  /**
   * Improved Google Translate API call with retry logic.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   * @param int $retry_count
   *   Number of retries attempted.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callGoogleTranslateApiWithRetry($text, $s_lang, $t_lang, $retry_count = 0) {
    $config = $this->configFactory->get('auto_translation.settings');
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');
    $max_retries = 3;

    try {
      $endpoint = 'https://translate.googleapis.com/translate_a/single';
      $query_params = [
        'client' => 'gtx',
        'sl' => $s_lang,
        'tl' => $t_lang,
        'dt' => 't',
        'q' => $text,
      ];

      if ($enable_debug) {
        $logger->info('Google Browser API - Using endpoint: @endpoint with client=gtx', [
          '@endpoint' => $endpoint,
        ]);
      }

      $options = [
        'verify' => true,  // Enable SSL verification for security
        'timeout' => 30,
        'connect_timeout' => 10,
        'headers' => [
          'User-Agent' => 'Mozilla/5.0 (compatible; DrupalAutoTranslation/1.0)',
          'Accept' => 'application/json, text/plain, */*',
          'Accept-Language' => 'en-US,en;q=0.9',
          'Referer' => 'https://translate.google.com/',
        ],
        'query' => $query_params,
      ];

      $startTime = microtime(true);
      $response = $this->httpClient->get($endpoint, $options);
      $elapsed = microtime(true) - $startTime;
      $data = Json::decode($response->getBody()->getContents());

      if ($enable_debug) {
        $logger->info('Google Browser API - Response received in @elapsed seconds', [
          '@elapsed' => round($elapsed, 2),
        ]);
      }

      if (!isset($data[0]) || !is_array($data[0])) {
        throw new \Exception('Invalid response format from translation API');
      }

      $translation = '';
      foreach ($data[0] as $segment) {
        if (isset($segment[0])) {
          $translation .= $segment[0];
        }
      }

      // Validate translation is not empty
      if (empty(trim($translation))) {
        if ($enable_debug) {
          $logger->warning('Google Browser API - Empty translation received for text: "@text"', [
            '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
          ]);
        }
        return $text; // Return original text instead of empty
      }

      if ($enable_debug) {
        $logger->info('Google Browser API - Translation successful for text: "@text"', [
          '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
        ]);
      }

      // Ensure proper HTML entity decoding for Google Browser API
      return htmlspecialchars_decode($translation, ENT_QUOTES | ENT_HTML5);

    } catch (RequestException $e) {
      $logger->warning('Translation API request failed (attempt @retry): @error', [
        '@retry' => $retry_count + 1,
        '@error' => $e->getMessage(),
      ]);

      // Retry logic for temporary failures.
      if ($retry_count < $max_retries && $e->getCode() >= 500) {
        sleep(pow(2, $retry_count)); // Exponential backoff.
        return $this->callGoogleTranslateApiWithRetry($text, $s_lang, $t_lang, $retry_count + 1);
      }

      return null;
    } catch (\Exception $e) {
      $logger->error('Translation API error: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Makes a direct call to Google Translate API.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callGoogleTranslateApi($text, $s_lang, $t_lang) {
    $endpoint = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . $s_lang . '&tl=' . $t_lang . '&dt=t&q=' . rawurlencode($text);
    $options = ['verify' => FALSE];

    try {
      $response = $this->httpClient->get($endpoint, $options);
      $data = Json::decode($response->getBody()->getContents());

      $translation = '';
      if (isset($data[0]) && is_array($data[0])) {
        foreach ($data[0] as $segment) {
          if (isset($segment[0])) {
            $translation .= $segment[0];
          }
        }
      }

      return $translation;
    }
    catch (RequestException $e) {
      $this->getLogger('auto_translation')->error('Translation API error: @error', ['@error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Makes a translation API call based on the specified provider.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   * @param string $provider
   *   The translation provider to use.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callProviderTranslationApi($text, $s_lang, $t_lang, $provider = 'google') {
    $config = $this->configFactory->get('auto_translation.settings');
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    switch ($provider) {
      case 'google':
        $api_enabled = $config->get('auto_translation_api_enabled') ?? NULL;
        if ($api_enabled) {
          // Use server API call for Google when API key is configured
          if ($enable_debug) {
            $logger->info('Using Google Server API for translation from @s_lang to @t_lang', [
              '@s_lang' => $s_lang,
              '@t_lang' => $t_lang,
            ]);
          }
          return $this->callGoogleServerTranslateApi($text, $s_lang, $t_lang);
        } else {
          // Use browser API call for Google
          if ($enable_debug) {
            $logger->info('Using Google Browser API for translation from @s_lang to @t_lang', [
              '@s_lang' => $s_lang,
              '@t_lang' => $t_lang,
            ]);
          }
          return $this->callGoogleTranslateApiWithRetry($text, $s_lang, $t_lang);
        }

      case 'libretranslate':
        if ($enable_debug) {
          $logger->info('Using LibreTranslate API for translation from @s_lang to @t_lang', [
            '@s_lang' => $s_lang,
            '@t_lang' => $t_lang,
          ]);
        }
        return $this->callLibreTranslateApi($text, $s_lang, $t_lang);

      case 'deepl':
        if ($enable_debug) {
          $logger->info('Using DeepL API for translation from @s_lang to @t_lang', [
            '@s_lang' => $s_lang,
            '@t_lang' => $t_lang,
          ]);
        }
        return $this->callDeepLTranslateApi($text, $s_lang, $t_lang);

      case 'drupal_ai':
        if ($this->moduleHandler->moduleExists('ai') && $this->moduleHandler->moduleExists('ai_translate')) {
          if ($enable_debug) {
            $logger->info('Using Drupal AI API for translation from @s_lang to @t_lang', [
              '@s_lang' => $s_lang,
              '@t_lang' => $t_lang,
            ]);
          }
          return $this->callDrupalAiTranslateApi($text, $s_lang, $t_lang);
        } else {
          $logger->error('AI translation modules are not installed.');
          return null;
        }

      case 'amazon':
        if ($enable_debug) {
          $logger->info('Using Amazon Translate API for translation from @s_lang to @t_lang', [
            '@s_lang' => $s_lang,
            '@t_lang' => $t_lang,
          ]);
        }
        return $this->callAmazonTranslateApi($text, $s_lang, $t_lang);

      default:
        $this->getLogger('auto_translation')->error('Unknown translation provider: @provider', ['@provider' => $provider]);
        return null;
    }
  }

  /**
   * Makes a direct call to Google Translate Server API using API key.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callGoogleServerTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('Google Server API - Using official Google Cloud Translate API with server key');
    }

    $maxRetries = 3;
    $startTime = microtime(true);

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
      try {
        $client = new TranslateClient(['key' => $apiKey]);
        $result = $client->translate($text, ['source' => $s_lang, 'target' => $t_lang]);

        // Validate translation is not empty
        if (empty(trim($result['text']))) {
          if ($enable_debug) {
            $logger->warning('Google Server API - Empty translation received for text: "@text"', [
              '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
            ]);
          }
          return $text; // Return original text instead of empty
        }

        if ($enable_debug) {
          $logger->info('Google Server API - Translation successful for text: "@text"', [
            '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
          ]);
        }

        // Ensure proper HTML entity decoding for Google Server API
        return htmlspecialchars_decode($result['text'], ENT_QUOTES | ENT_HTML5);
      }
      catch (\Exception $e) {
        $isRateLimitError = strpos($e->getMessage(), '429') !== false ||
                           strpos($e->getMessage(), 'quota') !== false ||
                           strpos($e->getMessage(), 'rate limit') !== false;

        if ($isRateLimitError && $attempt < $maxRetries - 1) {
          $elapsed = microtime(true) - $startTime;
          $waitTime = min(30, 2 * ($attempt + 1) ** 2 + mt_rand(0, 1000) / 1000.0); // max 30s
          if ($enable_debug) {
            $logger->warning('Google Server API Rate Limit - Retry @attempt after @wait seconds (elapsed: @elapsed)', [
              '@attempt' => $attempt + 1,
              '@wait' => round($waitTime, 2),
              '@elapsed' => round($elapsed, 2),
            ]);
          }
          sleep((int) $waitTime);
          continue;
        }

        $logger->error('Google Server API - Translation failed: @error', ['@error' => $e->getMessage()]);
        return null;
      }
    }

    return null;
  }

  /**
   * Makes a direct call to LibreTranslate API with retry logic and proper security.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callLibreTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);
    $endpoint = 'https://libretranslate.com/translate';
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('LibreTranslate API - Using endpoint: @endpoint', ['@endpoint' => $endpoint]);
    }

    $options = [
      'headers' => [
        'Content-Type' => 'application/json',
        'User-Agent' => 'Drupal Auto Translation Module/1.0',
      ],
      'json' => [
        'q' => $text,
        'source' => $s_lang,
        'target' => $t_lang,
        'format' => 'text',
        'api_key' => $apiKey,
      ],
      'timeout' => 30,
      'connect_timeout' => 10,
    ];

    $maxRetries = 3;
    $startTime = microtime(true);

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
      try {
        $response = $this->httpClient->post($endpoint, $options);
        $result = Json::decode($response->getBody()->getContents());
        $translated = $result['translatedText'] ?? null;

        // Validate translation is not empty
        if (empty(trim($translated))) {
          if ($enable_debug) {
            $logger->warning('LibreTranslate API - Empty translation received for text: "@text"', [
              '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
            ]);
          }
          return $text; // Return original text instead of empty
        }

        if ($enable_debug) {
          $logger->info('LibreTranslate API - Translation successful for text: "@text"', [
            '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
          ]);
        }

        // Ensure proper HTML entity decoding for LibreTranslate
        return $translated ? htmlspecialchars_decode($translated, ENT_QUOTES | ENT_HTML5) : null;
      }
      catch (RequestException $e) {
        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

        if (($statusCode == 429 || $statusCode >= 500) && $attempt < $maxRetries - 1) {
          $elapsed = microtime(true) - $startTime;
          $waitTime = min(30, 1 * ($attempt + 1) ** 2 + mt_rand(0, 1000) / 1000.0); // max 30s
          if ($enable_debug) {
            $logger->warning('LibreTranslate API @status - Retry @attempt after @wait seconds (elapsed: @elapsed)', [
              '@status' => $statusCode,
              '@attempt' => $attempt + 1,
              '@wait' => round($waitTime, 2),
              '@elapsed' => round($elapsed, 2),
            ]);
          }
          sleep((int) $waitTime);
          continue;
        }

        $logger->error('LibreTranslate API - Translation failed: @error', ['@error' => $e->getMessage()]);
        return null;
      }
    }

    return null;
  }

  /**
   * Makes a direct call to DeepL Translate API.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callDeepLTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $encryptedApiKey = $config->get('auto_translation_api_key');
    $apiKey = $this->decryptApiKey($encryptedApiKey);
    $deeplMode = $config->get('auto_translation_api_deepl_pro_mode') === false ? 'api-free' : 'api';
    $endpoint = sprintf('https://%s.deepl.com/v2/translate', $deeplMode);
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('DeepL API - Using endpoint: @endpoint (mode: @mode)', [
        '@endpoint' => $endpoint,
        '@mode' => $deeplMode,
      ]);
    }

    $options = [
      'headers' => [
        'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
        'Content-Type' => 'application/json',
        'User-Agent' => 'Drupal Auto Translation Module/1.0',
      ],
      'json' => [
        'text' => [$text],
        'source_lang' => $s_lang,
        'target_lang' => $t_lang,
      ],
      'timeout' => 30,
      'connect_timeout' => 10,
    ];

    $maxRetries = 8;
    $startTime = microtime(true);

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
      try {
        $response = $this->httpClient->post($endpoint, $options);
        $result = Json::decode($response->getBody()->getContents());
        $translated = $result['translations'][0]['text'] ?? null;

        // Validate translation is not empty
        if (empty(trim($translated))) {
          if ($enable_debug) {
            $logger->warning('DeepL API - Empty translation received for text: "@text"', [
              '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
            ]);
          }
          return $text; // Return original text instead of empty
        }

        if ($enable_debug) {
          $logger->info('DeepL API - Translation successful for text: "@text"', [
            '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
          ]);
        }

        return $translated ? htmlspecialchars_decode($translated, ENT_QUOTES | ENT_HTML5) : null;
      }
      catch (RequestException $e) {
        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

        if ($statusCode == 429 && $attempt < $maxRetries - 1) {
          $elapsed = microtime(true) - $startTime;
          $waitTime = min(60, 0.5 * ($attempt + 1) ** 2 + mt_rand(0, 1000) / 1000.0); // max 60s
          if ($enable_debug) {
            $logger->warning('DeepL API 429 - Retry @attempt after @wait seconds (elapsed: @elapsed)', [
              '@attempt' => $attempt + 1,
              '@wait' => round($waitTime, 2),
              '@elapsed' => round($elapsed, 2),
            ]);
          }
          sleep((int) $waitTime);
          continue;
        }

        $logger->error('DeepL API - Translation failed: @error', ['@error' => $e->getMessage()]);
        return null;
      }
    }

    return null;
  }

  /**
   * Makes a direct call to Drupal AI Translate API with retry logic.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or NULL on failure.
   */
  private function callDrupalAiTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $logger = $this->loggerFactory->get('auto_translation');
    $enable_debug = $config->get('enable_debug');

    if ($enable_debug) {
      $logger->info('Drupal AI API - Using AI Translate service');
    }

    $maxRetries = 3;
    $startTime = microtime(true);

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
      try {
        $container = $this->getContainer();
        $languageManager = $container->get('language_manager');
        $langFrom = $languageManager->getLanguage($s_lang);
        $langTo = $languageManager->getLanguage($t_lang);

        if (!$langFrom || !$langTo) {
          $logger->error('Drupal AI API - Invalid language codes: @s_lang to @t_lang', [
            '@s_lang' => $s_lang,
            '@t_lang' => $t_lang,
          ]);
          return null;
        }

        $translatedText = \Drupal::service('ai_translate.text_translator')->translateContent($text, $langTo, $langFrom);

        // Validate translation is not empty
        if (empty(trim($translatedText))) {
          if ($enable_debug) {
            $logger->warning('Drupal AI API - Empty translation received for text: "@text"', [
              '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
            ]);
          }
          return $text; // Return original text instead of empty
        }

        if ($enable_debug) {
          $logger->info('Drupal AI API - Translation successful for text: "@text"', [
            '@text' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : ''),
          ]);
        }

        // Ensure proper HTML entity decoding for Drupal AI
        return $translatedText ? htmlspecialchars_decode($translatedText, ENT_QUOTES | ENT_HTML5) : null;
      }
      catch (\Exception $e) {
        $isTemporaryError = strpos($e->getMessage(), 'timeout') !== false ||
                           strpos($e->getMessage(), 'connection') !== false ||
                           strpos($e->getMessage(), 'network') !== false;

        if ($isTemporaryError && $attempt < $maxRetries - 1) {
          $elapsed = microtime(true) - $startTime;
          $waitTime = min(20, 1 * ($attempt + 1) ** 2 + mt_rand(0, 500) / 1000.0); // max 20s
          if ($enable_debug) {
            $logger->warning('Drupal AI API Temporary Error - Retry @attempt after @wait seconds (elapsed: @elapsed)', [
              '@attempt' => $attempt + 1,
              '@wait' => round($waitTime, 2),
              '@elapsed' => round($elapsed, 2),
            ]);
          }
          sleep((int) $waitTime);
          continue;
        }

        $logger->error('Drupal AI API - Translation failed: @error', ['@error' => $e->getMessage()]);
        return null;
      }
    }

    return null;
  }

  /**
   * Makes a call to Amazon Translate API.
   *
   * @param string $text
   *   The text to translate.
   * @param string $s_lang
   *   The source language code.
   * @param string $t_lang
   *   The target language code.
   *
   * @return string|null
   *   The translated text or null if an error occurred.
   */
  private function callAmazonTranslateApi($text, $s_lang, $t_lang) {
    $config = $this->configFactory->get('auto_translation.settings');
    $logger = $this->getLogger('auto_translation');
    $maxRetries = 3;
    $baseWaitTime = 1; // Initial wait time in seconds

    // Get Amazon configuration
    $region = $config->get('amazon_region') ?: 'us-east-1';
    $encryptedAccessKey = $config->get('amazon_access_key');
    $encryptedSecretKey = $config->get('amazon_secret_key');
    $accessKey = $this->decryptApiKey($encryptedAccessKey);
    $secretKey = $this->decryptApiKey($encryptedSecretKey);

    if (empty($accessKey) || empty($secretKey)) {
      $logger->error('Amazon Translate API credentials not configured');
      return null;
    }

    // Validate AWS credential format
    if (!$this->isValidAwsCredentials($accessKey, $secretKey)) {
      $logger->error('Amazon Translate API credentials are invalid format');
      return null;
    }

    // Validate input
    $validation = $this->validateTranslationInput($text);
    if (!$validation['valid']) {
      $logger->error('Amazon Translate API - Invalid input: @error', ['@error' => $validation['error']]);
      return null;
    }
    $text = $validation['text'];

    // Map language codes to Amazon Translate format
    $amazonLangMap = [
      // Chinese variants
      'zh-hans' => 'zh',
      'zh-hant' => 'zh-TW',
      // Portuguese variants
      'pt-br' => 'pt',
      'pt-pt' => 'pt',
      // Spanish variants
      'es-mx' => 'es',
      'es-es' => 'es',
      // Norwegian
      'nb' => 'no',
      'nn' => 'no',
      // Arabic
      'ar' => 'ar',
      // French variants
      'fr-ca' => 'fr',
      'fr-fr' => 'fr',
      // German variants
      'de-de' => 'de',
      'de-at' => 'de',
      'de-ch' => 'de',
      // English variants
      'en-us' => 'en',
      'en-gb' => 'en',
      'en-ca' => 'en',
      'en-au' => 'en',
      // Italian
      'it-it' => 'it',
      // Dutch
      'nl-nl' => 'nl',
      'nl-be' => 'nl',
      // Swedish
      'sv-se' => 'sv',
      // Danish
      'da-dk' => 'da',
      // Finnish
      'fi-fi' => 'fi',
      // Polish
      'pl-pl' => 'pl',
      // Czech
      'cs-cz' => 'cs',
      // Hungarian
      'hu-hu' => 'hu',
      // Romanian
      'ro-ro' => 'ro',
      // Bulgarian
      'bg-bg' => 'bg',
      // Croatian
      'hr-hr' => 'hr',
      // Estonian
      'et-ee' => 'et',
      // Latvian
      'lv-lv' => 'lv',
      // Lithuanian
      'lt-lt' => 'lt',
      // Slovak
      'sk-sk' => 'sk',
      // Slovenian
      'sl-si' => 'sl',
    ];

    $sourceLang = $amazonLangMap[$s_lang] ?? $s_lang;
    $targetLang = $amazonLangMap[$t_lang] ?? $t_lang;

    // Validate supported languages for Amazon Translate
    $supportedLanguages = [
      'af', 'sq', 'am', 'ar', 'hy', 'az', 'bn', 'bs', 'bg', 'ca', 'zh', 'zh-TW',
      'hr', 'cs', 'da', 'fa-AF', 'nl', 'en', 'et', 'fa', 'tl', 'fi', 'fr',
      'fr-CA', 'ka', 'de', 'el', 'gu', 'ht', 'ha', 'he', 'hi', 'hu', 'is',
      'id', 'ga', 'it', 'ja', 'kn', 'kk', 'ko', 'lv', 'lt', 'mk', 'ms',
      'ml', 'mt', 'mn', 'no', 'ps', 'pl', 'pt', 'pt-PT', 'pa', 'ro', 'ru',
      'sr', 'si', 'sk', 'sl', 'so', 'es', 'es-MX', 'sw', 'sv', 'ta', 'te',
      'th', 'tr', 'uk', 'ur', 'uz', 'vi', 'cy'
    ];

    if (!in_array($sourceLang, $supportedLanguages, true)) {
      $logger->error('Amazon Translate API - Unsupported source language: @lang (mapped from @original)', [
        '@lang' => $sourceLang,
        '@original' => $s_lang,
      ]);
      return null;
    }

    if (!in_array($targetLang, $supportedLanguages, true)) {
      $logger->error('Amazon Translate API - Unsupported target language: @lang (mapped from @original)', [
        '@lang' => $targetLang,
        '@original' => $t_lang,
      ]);
      return null;
    }

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
      try {
        // Prepare request data
        $data = [
          'Text' => $text,
          'SourceLanguageCode' => $sourceLang,
          'TargetLanguageCode' => $targetLang,
        ];

        // Create the canonical request
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $service = 'translate';
        $host = "translate.{$region}.amazonaws.com";

        // Create headers
        $headers = [
          'Content-Type' => 'application/x-amz-json-1.1',
          'X-Amz-Target' => 'AWSShineFrontendService_20170701.TranslateText',
          'X-Amz-Date' => $timestamp,
          'Host' => $host,
        ];

        $canonicalHeaders = '';
        $signedHeaders = '';
        foreach ($headers as $key => $value) {
          $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
          $signedHeaders .= strtolower($key) . ';';
        }
        $signedHeaders = rtrim($signedHeaders, ';');

        $payload = json_encode($data);
        $payloadHash = hash('sha256', $payload);

        $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        // Calculate signature
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', $service, $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        // Create authorization header
        $authHeader = "{$algorithm} Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        $headers['Authorization'] = $authHeader;

        // Prepare cURL headers
        $requestHeaders = [];
        foreach ($headers as $key => $value) {
          $requestHeaders[] = "{$key}: {$value}";
        }

        // Use Drupal's HTTP client service instead of direct cURL
        $options = [
          'headers' => $headers,
          'body' => $payload,
          'timeout' => 30,
          'connect_timeout' => 10,
          'verify' => true,
          'http_errors' => false, // Don't throw exceptions on HTTP errors
        ];

        $startTime = microtime(true);
        try {
          $response = $this->httpClient->post("https://{$host}/", $options);
          $elapsed = microtime(true) - $startTime;
          $httpCode = $response->getStatusCode();
          $responseBody = $response->getBody()->getContents();
        } catch (RequestException $e) {
          $elapsed = microtime(true) - $startTime;
          throw new \Exception('HTTP request failed: ' . $e->getMessage());
        }

        if (empty($responseBody)) {
          throw new \Exception('Empty response received');
        }

        $responseData = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        if ($httpCode === 200 && isset($responseData['TranslatedText'])) {
          $logger->info('Amazon Translate API - Translation successful (attempt @attempt, @elapsed seconds)', [
            '@attempt' => $attempt,
            '@elapsed' => round($elapsed, 2),
          ]);
          return $responseData['TranslatedText'];
        }

        // Enhanced error handling for various Amazon Translate error codes
        if ($httpCode === 429 || $httpCode === 503) {
          // Rate limiting or service unavailable
          $waitTime = $baseWaitTime * pow(2, $attempt - 1); // Exponential backoff
          $logger->warning('Amazon Translate API - Rate limit/service unavailable (HTTP @code), retrying in @wait seconds (attempt @attempt/@max, @elapsed seconds)', [
            '@code' => $httpCode,
            '@wait' => $waitTime,
            '@attempt' => $attempt,
            '@max' => $maxRetries,
            '@elapsed' => round($elapsed, 2),
          ]);
          if ($attempt < $maxRetries) {
            sleep((int) $waitTime);
            continue;
          }
        }

        // Handle authentication errors
        if ($httpCode === 403) {
          $logger->error('Amazon Translate API - Authentication failed. Check your AWS credentials and permissions.');
          return null;
        }

        // Handle validation errors
        if ($httpCode === 400) {
          $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Bad request';
          $logger->error('Amazon Translate API - Validation error: @error', ['@error' => $errorMessage]);
          return null;
        }

        // Handle unsupported language pairs
        if (isset($responseData['__type']) && $responseData['__type'] === 'UnsupportedLanguagePairException') {
          $logger->error('Amazon Translate API - Unsupported language pair: @source to @target', [
            '@source' => $sourceLang,
            '@target' => $targetLang,
          ]);
          return null;
        }

        $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
        throw new \Exception("HTTP {$httpCode}: {$errorMessage}");

      } catch (\Exception $e) {
        $elapsed = isset($startTime) ? microtime(true) - $startTime : 0;

        if ($attempt < $maxRetries) {
          $waitTime = $baseWaitTime * pow(2, $attempt - 1);
          $logger->warning('Amazon Translate API - Attempt @attempt failed, retrying in @wait seconds (error: @error, @elapsed seconds)', [
            '@attempt' => $attempt,
            '@wait' => $waitTime,
            '@error' => $e->getMessage(),
            '@elapsed' => round($elapsed, 2),
          ]);
          sleep((int) $waitTime);
          continue;
        }

        $logger->error('Amazon Translate API - Translation failed: @error', ['@error' => $e->getMessage()]);
        return null;
      }
    }

    return null;
  }

  /**
   * Validates and sanitizes text input for translation.
   *
   * @param string $text
   *   The text to validate.
   * @param int $maxLength
   *   Maximum allowed text length (default: 10000 characters).
   *
   * @return array
   *   Array with 'valid' (bool) and 'text' (string) or 'error' (string).
   */
  private function validateTranslationInput($text, $maxLength = 10000) {
    // Check if text is empty
    if (empty(trim($text))) {
      return ['valid' => false, 'error' => 'Empty text provided'];
    }

    // Check text length
    if (strlen($text) > $maxLength) {
      return ['valid' => false, 'error' => "Text too long (max {$maxLength} characters)"];
    }

    // Sanitize text using modern approach instead of deprecated FILTER_SANITIZE_STRING
    // Remove null bytes and control characters except newlines, tabs, and carriage returns
    $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    // Additional security check for script tags and other dangerous content
    if (preg_match('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', $sanitized)) {
      return ['valid' => false, 'error' => 'Potentially dangerous content detected'];
    }

    // Check for other potentially dangerous HTML elements
    $dangerous_tags = ['<object', '<embed', '<applet', '<iframe', '<frame', '<form'];
    foreach ($dangerous_tags as $tag) {
      if (stripos($sanitized, $tag) !== false) {
        return ['valid' => false, 'error' => 'Potentially dangerous HTML content detected'];
      }
    }

    return ['valid' => true, 'text' => $sanitized];
  }

  /**
   * Validates AWS credentials format.
   *
   * @param string $accessKey
   *   The AWS access key.
   * @param string $secretKey
   *   The AWS secret key.
   *
   * @return bool
   *   TRUE if credentials are valid format, FALSE otherwise.
   */
  private function isValidAwsCredentials($accessKey, $secretKey) {
    // AWS access key should be 20 characters, alphanumeric
    if (!preg_match('/^[A-Z0-9]{20}$/', $accessKey)) {
      return FALSE;
    }

    // AWS secret key should be 40 characters, alphanumeric and symbols
    if (!preg_match('/^[A-Za-z0-9\/+=]{40}$/', $secretKey)) {
      return FALSE;
    }

    return TRUE;
  }

}
