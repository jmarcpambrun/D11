<?php

namespace Drupal\ai_integration_eca\Service;

use Drupal\ai\OperationType\GenericType\AbstractFileBase;

/**
 * Converts ECA token values to/from AI file value objects.
 */
interface AiFileHelperInterface {

  /**
   * Input mode: the token holds a managed file ID.
   */
  const INPUT_FID = 'fid';

  /**
   * Input mode: the token holds a stream URI.
   *
   * The scheme has to be a stream wrapper Drupal manages; a bare filesystem
   * path is not a valid value for this mode.
   */
  const INPUT_PATH = 'path';

  /**
   * Input mode: the token holds raw binary data.
   */
  const INPUT_BINARY = 'binary';

  /**
   * Output mode: store a managed file entity ID (default).
   */
  const OUTPUT_MANAGED_FILE = 'managed_file';

  /**
   * Output mode: store an unmanaged file path (stream URI).
   */
  const OUTPUT_PATH = 'path';

  /**
   * Output mode: store raw binary data.
   */
  const OUTPUT_BINARY = 'binary';

  /**
   * Convert a scalar token value into an AI file value object.
   *
   * @param string $value
   *   The token value: a managed file ID, a stream URI, or raw binary.
   * @param string $type
   *   One of self::INPUT_FID, self::INPUT_PATH or self::INPUT_BINARY.
   * @param class-string<T> $fileClass
   *   A fully-qualified subclass of AbstractFileBase (ImageFile, AudioFile,
   *   VideoFile).
   *
   * @return T
   *   The populated file value object, of the requested class.
   *
   * @throws \InvalidArgumentException
   *   When the class is wrong, the file cannot be loaded/read, or the type is
   *   unknown. For self::INPUT_PATH this also covers a URI with no scheme,
   *   with a scheme that is not an accepted stream wrapper, or that points
   *   outside its own wrapper.
   *
   * @template T of \Drupal\ai\OperationType\GenericType\AbstractFileBase
   */
  public function resolveInput(string $value, string $type, string $fileClass): AbstractFileBase;

  /**
   * Convert AI output file objects into a list of token-storable references.
   *
   * @param \Drupal\ai\OperationType\GenericType\AbstractFileBase[] $files
   *   The generated files.
   * @param string $type
   *   One of self::OUTPUT_MANAGED_FILE, self::OUTPUT_PATH, self::OUTPUT_BINARY.
   * @param string $destination
   *   The stream-wrapper directory used for path/managed output.
   * @param string $filename
   *   Optional filename to use for written files. When empty, each file keeps
   *   the name provided by the model. Either way it is reduced to a bare
   *   filename, so writes stay inside $destination. With multiple files,
   *   collisions are renamed automatically.
   *
   * @return array
   *   A list of file IDs (int), URIs (string) or binaries (string).
   */
  public function buildOutput(array $files, string $type, string $destination = 'public://', string $filename = ''): array;

  /**
   * Build the reusable "Input source" select form element.
   */
  public function inputElement(string $default = self::INPUT_PATH): array;

  /**
   * Build the reusable "Output as" select form element.
   */
  public function outputElement(string $default = self::OUTPUT_MANAGED_FILE): array;

  /**
   * Build the reusable "Destination directory" textfield element.
   */
  public function destinationElement(string $default = 'public://'): array;

  /**
   * Build the reusable "Filename" textfield element.
   */
  public function filenameElement(string $default = ''): array;

}
