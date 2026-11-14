<?php

namespace Drupal\ai_integration_eca\Service;

use Drupal\ai\OperationType\GenericType\AbstractFileBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Psr\Log\LoggerInterface;

/**
 * Converts ECA token values to/from AI file value objects.
 */
class AiFileHelper implements AiFileHelperInterface {

  use StringTranslationTrait;

  /**
   * The stream wrapper schemes a file input may use out of the box.
   *
   * Core's three file storage wrappers, which is where a Drupal site keeps the
   * files it owns. A site whose storage lives elsewhere adds its scheme in
   * settings.php; see self::SETTING_ALLOWED_SCHEMES.
   */
  protected const DEFAULT_ALLOWED_SCHEMES = [
    'private',
    'public',
    'temporary',
  ];

  /**
   * The settings.php key that adds schemes to the default set.
   *
   * Kept in settings.php rather than in configuration on purpose: it decides
   * what the site is willing to open, so it should not be reachable through the
   * UI or arrive with an imported configuration. Same reasoning as core's
   * trusted_host_patterns.
   *
   * @code
   * $settings['ai_integration_eca_allowed_schemes'] = ['s3'];
   * @endcode
   */
  protected const SETTING_ALLOWED_SCHEMES = 'ai_integration_eca_allowed_schemes';

  /**
   * Constructs the helper.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected LoggerInterface $logger,
    protected StreamWrapperManagerInterface $streamWrapperManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolveInput(string $value, string $type, string $fileClass): AbstractFileBase {
    if ($fileClass !== AbstractFileBase::class && !is_subclass_of($fileClass, AbstractFileBase::class)) {
      throw new \InvalidArgumentException(sprintf('"%s" is not an AbstractFileBase subclass.', $fileClass));
    }
    /** @var \Drupal\ai\OperationType\GenericType\AbstractFileBase $file */
    $file = new $fileClass();

    switch ($type) {
      case self::INPUT_FID:
        $entity = $this->entityTypeManager->getStorage('file')->load($value);
        if (!$entity instanceof FileInterface) {
          throw new \InvalidArgumentException(sprintf('No managed file with ID "%s".', $value));
        }
        $file->setFileFromFile($entity);
        break;

      case self::INPUT_PATH:
        $this->validateInputUri($value);
        $file->setFileFromUri($value);
        break;

      case self::INPUT_BINARY:
        $file->setBinary($value);
        break;

      default:
        throw new \InvalidArgumentException(sprintf('Unknown input type "%s".', $type));
    }

    return $file;
  }

  /**
   * Checks that a path input names a readable file in the site's own storage.
   *
   * The value is an ECA token, so what it contains is decided by the flow
   * rather than by the action, and it goes straight into a stream read. Keep it
   * to the wrappers Drupal manages before anything opens it.
   *
   * @param string $value
   *   The raw token value.
   *
   * @throws \InvalidArgumentException
   *   When the value is empty, has no scheme, has a scheme that is not an
   *   accepted stream wrapper, points outside its own wrapper, or is not a
   *   readable file.
   */
  protected function validateInputUri(string $value): void {
    if ($value === '') {
      throw new \InvalidArgumentException('The file input is empty.');
    }

    // No scheme means an operating system path, absolute or relative, which
    // addresses rather more than the site's own files.
    $scheme = StreamWrapperManager::getScheme($value);
    if ($scheme === FALSE) {
      throw $this->refuse('The file input "@uri" is not a stream URI; use a scheme such as public://, private:// or temporary://.', $value);
    }

    // Both halves matter: the scheme has to be one the site accepts, and it
    // still has to be a wrapper Drupal actually knows about, so a stale or
    // mistyped entry in settings.php fails here rather than further down.
    $scheme = mb_strtolower($scheme);
    if (!in_array($scheme, $this->allowedSchemes(), TRUE) || !$this->streamWrapperManager->isValidScheme($scheme)) {
      throw $this->refuse('The file input "@uri" does not use an accepted scheme; nothing was read.', $value);
    }

    // Core's local stream wrappers already decline to resolve a target outside
    // their own directory. Repeating it here also covers wrappers that do not
    // extend LocalStream, and gives a clearer reason than a missing file.
    $target = StreamWrapperManager::getTarget($value);
    if ($target === FALSE || $target === '' || preg_match('#(^|/)\.\.(/|$)#', $target) === 1) {
      throw $this->refuse('The file input "@uri" is not a valid path inside its own stream wrapper; nothing was read.', $value);
    }

    // is_file() rather than file_exists(): a directory exists but cannot be
    // read as a file, and would only surface later as a warning.
    if (!is_file($value)) {
      throw new \InvalidArgumentException(sprintf('The path "%s" is not a readable file.', $value));
    }
  }

  /**
   * The stream wrapper schemes a file input may use on this site.
   *
   * Additive rather than a replacement: a site that adds its object storage
   * scheme should not have to restate the core three, and cannot lock itself
   * out of its own files by forgetting one.
   *
   * @return string[]
   *   The accepted schemes, lower-cased.
   */
  protected function allowedSchemes(): array {
    $extra = Settings::get(self::SETTING_ALLOWED_SCHEMES, []);
    if (!is_array($extra)) {
      $this->logger->error('The @setting setting is expected to be an array of stream wrapper schemes; it was ignored.', ['@setting' => self::SETTING_ALLOWED_SCHEMES]);
      $extra = [];
    }
    $extra = array_map('mb_strtolower', array_filter($extra, 'is_string'));

    return array_values(array_unique(array_merge(self::DEFAULT_ALLOWED_SCHEMES, $extra)));
  }

  /**
   * Logs a rejected file input and returns the exception to throw for it.
   *
   * Callers swallow the exception and produce no result, as they do for every
   * other failure here. Without a log entry an administrator would have nothing
   * to look at when a flow quietly stops producing output.
   *
   * @param string $message
   *   The log message, with an "@uri" placeholder.
   * @param string $value
   *   The rejected value.
   *
   * @return \InvalidArgumentException
   *   The exception for the caller to throw.
   */
  protected function refuse(string $message, string $value): \InvalidArgumentException {
    $this->logger->warning($message, ['@uri' => $value]);

    return new \InvalidArgumentException(str_replace('@uri', $value, $message));
  }

  /**
   * {@inheritdoc}
   */
  public function buildOutput(array $files, string $type, string $destination = 'public://', string $filename = ''): array {
    if ($destination === '') {
      $destination = 'public://';
    }
    $result = [];

    // For unmanaged path output, prepare the destination directory once. If it
    // cannot be created, return the (empty) result without attempting writes.
    if ($type === self::OUTPUT_PATH
      && !$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      return $result;
    }

    foreach ($files as $file) {
      // A configured filename overrides the file's own name. When more than one
      // file is produced, the file system renames collisions (name, name_0, …).
      // Neither candidate is constrained — the configured one is
      // token-replaced, the fallback is whatever the provider named its
      // response — so reduce whichever is used to a bare filename.
      $name = $this->sanitizeFilename($filename !== '' ? $filename : $file->getFilename());
      // Fill in a generated name here rather than per output mode:
      // getAsFileEntity() falls back to the provider's own filename when handed
      // an empty one, which would step around the line above.
      if ($name === '') {
        $name = uniqid();
      }
      switch ($type) {
        case self::OUTPUT_BINARY:
          $result[] = $file->getBinary();
          break;

        case self::OUTPUT_PATH:
          $uri = rtrim($destination, '/') . '/' . $name;
          $written = $this->fileSystem->saveData($file->getBinary(), $uri, FileExists::Rename);
          // Skip files that fail to write rather than storing a falsy URI.
          if (!empty($written)) {
            $result[] = $written;
          }
          else {
            $this->logger->error('Failed to write generated file to @uri; it was dropped from the result.', ['@uri' => $uri]);
          }
          break;

        case self::OUTPUT_MANAGED_FILE:
          $result[] = (int) $file->getAsFileEntity($destination, $name)->id();
          break;

        default:
          throw new \InvalidArgumentException(sprintf('Unknown output type "%s".', $type));
      }
    }
    return $result;
  }

  /**
   * Reduces a filename to its basename so it stays inside the destination.
   *
   * @param string $name
   *   The candidate filename.
   *
   * @return string
   *   The bare filename, or an empty string when nothing usable remains. The
   *   callers already substitute a generated name for an empty one.
   */
  protected function sanitizeFilename(string $name): string {
    // Normalize backslashes first: basename() only treats them as separators
    // on Windows, and the destination is a stream URI in either case.
    $name = basename(str_replace('\\', '/', $name));

    return ($name === '.' || $name === '..') ? '' : $name;
  }

  /**
   * {@inheritdoc}
   */
  public function inputElement(string $default = self::INPUT_PATH): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Input source'),
      '#options' => [
        self::INPUT_FID => $this->t('Managed file ID'),
        self::INPUT_PATH => $this->t('File path or URI'),
        self::INPUT_BINARY => $this->t('Raw binary'),
      ],
      '#default_value' => $default,
      '#description' => $this->t('How the input token value should be interpreted.'),
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function outputElement(string $default = self::OUTPUT_MANAGED_FILE): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Output as'),
      '#options' => [
        self::OUTPUT_MANAGED_FILE => $this->t('Managed file ID'),
        self::OUTPUT_PATH => $this->t('File path (unmanaged)'),
        self::OUTPUT_BINARY => $this->t('Raw binary'),
      ],
      '#default_value' => $default,
      '#description' => $this->t('How each generated file is stored. The result token is always a list.'),
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function destinationElement(string $default = 'public://'): array {
    return [
      '#type' => 'textfield',
      '#title' => $this->t('Destination directory'),
      '#default_value' => $default,
      '#description' => $this->t('Stream-wrapper directory for written files, e.g. public://ai. Used by managed file and file path output.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filenameElement(string $default = ''): array {
    return [
      '#type' => 'textfield',
      '#title' => $this->t('Filename'),
      '#default_value' => $default,
      '#description' => $this->t('Optional filename (including extension) for written files. Leave empty to use the name provided by the model. With multiple files, collisions are renamed automatically. Used by managed file and file path output.'),
      // The value is a filename that tokens are replaced in, not the name of a
      // token, so it has to accept "[node:title].png" and "[node:title]" alike.
      '#eca_token_replacement' => TRUE,
    ];
  }

}
