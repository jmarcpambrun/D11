<?php

namespace Drupal\maestro_ai_task\MaestroAiTaskAPI;

use Drupal\file\Entity\File;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_ai_task\Entity\MaestroAIStorage;

/**
 * MaestroAiTaskAPI API class.
 *
 * Provides the Maestro AI Task API.
 */
class MaestroAiTaskAPI {
  
  /**
   * The only image types written to disk, keyed by IMAGETYPE_* constant.
   *
   * The value is the file extension used when saving.
   */
  const ALLOWED_IMAGE_TYPES = [
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_WEBP => 'webp',
  ];

  /**
   * Maximum size of media fetched from a remote URL (25MB).
   */
  const MAX_REMOTE_MEDIA_BYTES = 26214400;

  /**
   * getAiStorageEntityDefinitionsInTemplate  
   *   Provide a template machine name and this method will look for all AI tasks in that template and return to you  
   *   an array of defined Maestro AI Task Storage entities in use.  
   *   This method will omit the Maestro AI Task's reserved "maestro_ai_task_history" for potential future use.
   *
   * @param  string $templateMachineName  
   *   The template machine name.
   * @param  bool $return_as_options_array  
   *   Default is TRUE -- will return an options array keyed by the name of the variable with value of the variable.  
   *   FALSE will return a simple value array.
   * @return array  
   *   Empty array on failure or absence of storage entities.  Success will be an array with count > 0.  
   */
  public static function getAiStorageEntityDefinitionsInTemplate($templateMachineName, $return_as_options_array = TRUE) {
    $definitions = [];
    $template = MaestroEngine::getTemplate($templateMachineName);
    foreach ($template->tasks as $templateTask) {
      $tasktype = $templateTask['tasktype'] ?? NULL;
      if ($tasktype == 'MaestroAITask') {
        // See if there's any other entities already set.
        $ai_var = $templateTask['ai_return_into_ai_variable'] ?? NULL;
        if ($ai_var && $ai_var != 'maestro_ai_task_history') {
          if($return_as_options_array) {
            $definitions[strval($ai_var)] = $ai_var;
          }
          else {
            $definitions[] = $ai_var;
          }
          
        }
      }
    }
    return $definitions;
  }


  /**
   * createMaestroAiTaskCapabilityPlugin  
   *   Creates and instance of a Maestro AI Task Capability Plugin.  
   *
   * @param  string $selected_ai_provider  
   *   The provider we're trying to instantiate for.  
   * @param  array $config  
   *   Array of config options.  The currently supported options are  
   *    [  
   *      'task' => ..                  // A Maestro Task configuration array  
   *      'templateMachineName' => ...  // The template machine name  
   *      'queueID' => ...              // A queue ID if we're executing  
   *      'processID' => ...            // A process ID if we're executing  
   *      'prompt' => ...               // The AI prompt  
   *      'form_state' => ...           // FormState if required or even available  
   *      'form' => ...                 // The Form array if required or even available  
   *    ]  
   *   
   *   Any config option can be blank/NULL, but that may affect how the capability plugin works.  
   * @return NULL|MaestroAiTaskCapabilitiesInterface  
   *   Returns NULL on failure.  An instance of the Maestro AI Task Capability plugin on success.  
   */
  public static function createMaestroAiTaskCapabilityPlugin($selected_ai_provider, array $config) {
    $maestro_capability = NULL;
    $ai_task_plugin_manager = \Drupal::service('plugin.manager.maestro_ai_task_capabilities');
    $implemented_maestro_ai_task_capabilities = $ai_task_plugin_manager->getDefinitions(); // Array of available Capability plugins we offer.
    $chosen_capability = $implemented_maestro_ai_task_capabilities[$selected_ai_provider] ?? NULL;
    if($chosen_capability) {
      $maestro_capability = $ai_task_plugin_manager->createInstance(
        $selected_ai_provider, 
        $config
      );
    }
    return $maestro_capability;
  }

  /**
   * Get the AI Storage Entity.
   *
   *   Fetches the Maestro AI Task storage entity defined in AI tasks.
   *   This requires the entity's unique_id (machine name) to be provided
   *   to this method as defined in the AI Task.
   *
   * @param string $unique_id
   *   The unique ID/machine_name given to the AI Storage entity in the AI task.
   * @param int $process_id
   *   The process ID this entity lives in.
   *
   * @return Drupal\maestro_ai_task\Entity\MaestroAIStorage[]|null
   *   On success, you'll get an array of MaestroAIStorage entity(ies) fully
   *   loaded. NULL on failure.
   */
  public static function getAiStorageEntityByUniqueId($unique_id, $process_id) {
    $entity = NULL;
    $storage_query = \Drupal::entityTypeManager()->getStorage('maestro_ai_storage')->getQuery()
      ->condition('machine_name', $unique_id)
      ->condition('process_id', $process_id)
      ->accessCheck(FALSE);
    $ai_storage_result = $storage_query->execute();
    if ($ai_storage_result) {
      $entity = MaestroAIStorage::loadMultiple($ai_storage_result);
    }

    return $entity;
  }

  /**
   * Get the Ai Storage Value By UniqueID.
   *
   *   Provide the process ID and the unique machine name for the AI Task's
   *   storage entity and this method will Return to you the value stored.
   *   PLEASE NOTE:  This method will ONLY return values when there is a
   *   singular entity tagged with the machine name provided.
   *   You will get a NULL return when multiple AI Storage entities exist
   *   in the same process with the same machine name.
   *
   * @param mixed $unique_id
   *   The unique ID/machine_name given to the AI Storage entity in the AI task.
   * @param int $process_id
   *   The process ID this entity lives in.
   * @param string $data_array_key
   *   Optional.  The data arary key that holds the output you're after.  By default, Maestro AI Task saves
   *   The data in a 'response' key.  Unless you specify otherwise, this is the key that is returned.  
   * 
   * @return string|null
   *   On success, this will return the value stored by the AI storage entity.
   *   Else, NULL.
   */
  public static function getAiStorageValueByUniqueId($unique_id, $process_id, $data_array_key = 'response') {
    $return_value = NULL;
    $ai_storage_entities = self::getAiStorageEntityByUniqueId($unique_id, $process_id);
    if (count($ai_storage_entities) == 1) {
      /** @var \Drupal\maestro_ai_task\Entity\MaestroAIStorage $aiEntity */
      $aiEntity = current($ai_storage_entities);
      $saved_value = $aiEntity->ai_storage->getValue() ?? NULL;
      if ($saved_value) {
         if(array_key_exists($data_array_key, $saved_value[0])) {
          $return_value = $saved_value[0][$data_array_key];
        }
        else { // Otherwise, return the whole array/blob
          $return_value = $saved_value[0];
        }
      }
    }
    return $return_value;
  }

  /**
   * Convert a base64 AI Storage entity to an Image file.
   *
   *   If you have an image stored in the Maestro AI Storage entity as base64, 
   *   use this method to extract it to a physical file and place it on the disk.
   *
   *   Default filename created will be:
   *   sites/default/files/private/ai_image-[UNIQUE_ID]-[PROCESS_ID].[extension]
   *   Where
   *     [UNIQUE_ID] is the unique ID for the Maestro AI Entity you configured
   *     in your Maestro AI task.
   *     [PROCESS_ID] is the process ID the image was associated with.
   *     [extension] is automatically generated from the base_64 decoded
   *     stored data.
   *
   * @param string $unique_id
   *   The unique ID/machine_name given to the AI Storage entity in the AI task.
   * @param mixed $process_id
   *   The process ID this entity lives in.
   * @param string $file_path_base
   *   [optional] Where you'd like to store the file. Defaults to the PRIVATE
   *   files folder with a filename of
   *   ai_image-[UNIQUEID]-[PROCESSID].[IMAGEEXTENSION]
   *   Please note, if the image extension cannot be determined, the extension will be "unknown".
   *
   * @return array
   *   Returns an empty array on failure.
   *   Returns an array with 2 keys on success:
   *   file => the file path, mime_type => the mime type of the image
   */
  public static function convertBase64AiStorageImageToFile($unique_id, $process_id, $file_path_base = NULL) {
    $return_array = [];
    // Default extension is unknown.
    $extension = 'unknown';
    // Default mime type is unknown.
    $mime_type = 'unknown';
    $image_string = self::getAiStorageValueByUniqueID($unique_id, $process_id);
    if ($image_string) {
      $return_array = self::saveBase64ImageToFile($image_string, $file_path_base, $unique_id, $process_id);
    }

    return $return_array;
  }
  
  /**
   * saveBase64ImageToFile  
   * 
   *   This method will take a base64 encoded image string and save it to a file.  
   *   This method differs from the convertBase64AiStorageImageToFile method in that
   *   as this method requires the image string to be passed in directly.
   * 
   *   If you do not provide a file_path_base, the unique ID and process ID are used to create 
   *   the filename.  The file is saved to the private files directory.
   *
   * @param  mixed $image_string  
   *   The base64 encoded image string to be saved.
   * @param  mixed $file_path_base  
   *   The base path to save the file to.  If not provided, the private files directory
   *   will be used, using the unique ID and process ID to create the filename.  
   * @param  mixed $unique_id  
   *   The unique ID/machine_name given to the AI Storage entity in the AI task.  
   * @param  mixed $process_id  
   *   The process ID this entity lives in.  
   * @return array  
   *   Returns an empty array on failure.  Otherwise an array with 2 keys:
   *   file => the file path, mime_type => the mime type of the image
   */
  public static function saveBase64ImageToFile($image_string, $file_path_base = NULL, $unique_id = NULL, $process_id = NULL) {
    $return_array = [];
    // Remove the data URI preamble (if it exists). The type it declares is
    // ignored: the stored value can come from an AI response, so it is not
    // trusted to pick the file extension. The real type is detected from the
    // decoded bytes instead.
    $image_string = preg_replace('/^data:[^,]*,/', '', trim((string) $image_string));
    $image_data = base64_decode($image_string);
    $image_info = $image_data ? @getimagesizefromstring($image_data) : FALSE;
    $image_type = $image_info[2] ?? NULL;
    if (!$image_info || !isset(self::ALLOWED_IMAGE_TYPES[$image_type])) {
      \Drupal::logger('Maestro AI API')->error('Unable to save the AI storage value as an image: it is not a base64 encoded PNG, JPEG, GIF or WebP image.');
      return $return_array;
    }
    $extension = self::ALLOWED_IMAGE_TYPES[$image_type];
    $mime_type = image_type_to_mime_type($image_type);

    if (!$file_path_base) {
      // We use a predefined private files path.
      // If this fails, good chance that the private files path doesn't exist.
      // Both values end up in the filename, so restrict them to safe characters.
      $unique_id = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $unique_id);
      if($unique_id === '') {
        $unique_id = 'no-unique-id';
      }
      $process_id = intval($process_id) ?: 'no-process-id';
      $file_path = 'private://ai-image-' . $unique_id . '-' . $process_id . '.' . $extension;
      $file_path = \Drupal::service('file_system')->realpath($file_path);
    }
    else {
      $file_path = $file_path_base;
    }

    // Suppress the file_put_contents warning when the file location
    // can't be found.
    // We handle this as a blank array return and avoids warnings showing.
    if ($file_path && @file_put_contents($file_path, $image_data)) {
      $return_array = [
        'file' => $file_path,
        'mime_type' => $mime_type,
      ];
    }
    else {
      // Likely due to the private files folder (or the folder passed in)
      // doesn't exist or hasn't been configured.
      \Drupal::logger('Maestro AI API')->error(t('Unable to save file to the location specified. :file', [':file' => $file_path]));
    }

    return $return_array;
  }

  /**
   * Loads the image or audio media referenced by a process variable.
   *
   *   Process variable values often come from end users (webform submissions,
   *   interactive tasks, AI tools), so the value is never used as a raw path
   *   or stream. It must be one of:
   *   - A managed file ID that the process initiator is allowed to download.
   *   - An https:// URL whose host resolves only to public IP addresses.
   *   The media type is detected from the content, not from the file name,
   *   entity or HTTP headers.
   *
   * @param mixed $pv_value
   *   The process variable value.
   * @param int $process_id
   *   The process ID the process variable belongs to.
   * @param string[] $allowed_mime_prefixes
   *   The accepted media types, for example ['image/'] or ['audio/', 'video/'].
   *
   * @return array|null
   *   NULL on failure (the reason is logged). Otherwise an array with 2 keys:
   *   binary => the media contents, mime => the detected mime type.
   */
  public static function loadProcessVariableMedia($pv_value, $process_id, array $allowed_mime_prefixes) {
    if ($pv_value === FALSE || $pv_value === NULL) {
      return NULL;
    }
    $pv_value = trim((string) $pv_value);
    if (ctype_digit($pv_value)) {
      $media = self::loadManagedFileForProcess((int) $pv_value, $process_id);
    }
    elseif (str_starts_with(strtolower($pv_value), 'https://')) {
      $media = self::fetchRemoteMedia($pv_value);
    }
    else {
      \Drupal::logger('Maestro AI API')->error('Process @pid: the media source process variable must hold a file ID or an https:// URL.', ['@pid' => $process_id]);
      return NULL;
    }

    return $media ? self::validateMedia($media, $allowed_mime_prefixes) : NULL;
  }

  /**
   * Fetches media from a public https:// URL.
   *
   *   Guards against server side request forgery: only https is allowed, the
   *   host must resolve exclusively to public IP addresses, the connection is
   *   pinned to the vetted address (so DNS cannot be re-bound between the
   *   check and the request), redirects are not followed and the size is
   *   capped.
   *
   * @param string $url
   *   The URL to fetch.
   *
   * @return array|null
   *   NULL on failure (the reason is logged). Otherwise an array with 2 keys:
   *   binary => the response body, mime => the response's Content-Type.
   */
  public static function fetchRemoteMedia($url) {
    $logger = \Drupal::logger('Maestro AI API');
    $parts = parse_url($url);
    $host = $parts['host'] ?? '';
    if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
      $logger->error('Refused to fetch media: @url is not a valid https:// URL.', ['@url' => $url]);
      return NULL;
    }
    if (!function_exists('curl_init')) {
      // Pinning the vetted IP address requires the cURL handler.
      $logger->error('Refused to fetch media from @url: the PHP cURL extension is required for remote media.', ['@url' => $url]);
      return NULL;
    }

    $port = $parts['port'] ?? 443;
    $ip_literal = trim($host, '[]');
    $is_ip_literal = filter_var($ip_literal, FILTER_VALIDATE_IP) !== FALSE;
    $ips = $is_ip_literal ? [$ip_literal] : (gethostbynamel($host) ?: []);
    $public_ip_flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    if (defined('FILTER_FLAG_GLOBAL_RANGE')) {
      $public_ip_flags |= FILTER_FLAG_GLOBAL_RANGE;
    }
    if (!$ips) {
      $logger->error('Refused to fetch media: unable to resolve the host of @url.', ['@url' => $url]);
      return NULL;
    }
    foreach ($ips as $ip) {
      if (filter_var($ip, FILTER_VALIDATE_IP, $public_ip_flags) === FALSE) {
        $logger->error('Refused to fetch media: @url resolves to a private or reserved address.', ['@url' => $url]);
        return NULL;
      }
    }

    $curl_options = [CURLOPT_MAXFILESIZE => self::MAX_REMOTE_MEDIA_BYTES];
    if (!$is_ip_literal) {
      $curl_options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . $ips[0]];
    }
    try {
      $response = \Drupal::httpClient()->get($url, [
        'allow_redirects' => FALSE,
        'connect_timeout' => 10,
        'timeout' => 60,
        'http_errors' => FALSE,
        'force_ip_resolve' => $is_ip_literal ? NULL : 'v4',
        'curl' => $curl_options,
      ]);
    }
    catch (\Exception $e) {
      $logger->error('Unable to fetch media from @url: @message', ['@url' => $url, '@message' => $e->getMessage()]);
      return NULL;
    }

    $binary = (string) $response->getBody();
    if ($response->getStatusCode() !== 200 || $binary === '' || strlen($binary) > self::MAX_REMOTE_MEDIA_BYTES) {
      $logger->error('Unable to fetch media from @url: HTTP @status, @bytes bytes.', [
        '@url' => $url,
        '@status' => $response->getStatusCode(),
        '@bytes' => strlen($binary),
      ]);
      return NULL;
    }

    return [
      'binary' => $binary,
      'mime' => trim(explode(';', $response->getHeaderLine('Content-Type'))[0]),
    ];
  }

  /**
   * Loads a managed file on behalf of a process's initiator.
   *
   * @param int $fid
   *   The file ID.
   * @param int $process_id
   *   The process ID. The file is only returned when the user who started
   *   this process may download it (or uploaded it).
   *
   * @return array|null
   *   NULL on failure (the reason is logged). Otherwise an array with 2 keys:
   *   binary => the file contents, mime => the file entity's mime type.
   */
  protected static function loadManagedFileForProcess($fid, $process_id) {
    $logger = \Drupal::logger('Maestro AI API');
    $file = File::load($fid);
    if (!$file) {
      $logger->error('Process @pid: unable to load file id @fid.', ['@pid' => $process_id, '@fid' => $fid]);
      return NULL;
    }

    // Use the initiator stored on the process entity, not the "initiator"
    // process variable, as process variables can be overwritten.
    $process = MaestroEngine::getProcessEntryById($process_id);
    $initiator = $process ? $process->getOwner() : NULL;
    $allowed = $initiator && (
      $file->access('download', $initiator)
      || (!$initiator->isAnonymous() && $file->getOwnerId() == $initiator->id())
    );
    if (!$allowed) {
      $logger->error('Process @pid: the process initiator does not have access to file id @fid.', ['@pid' => $process_id, '@fid' => $fid]);
      return NULL;
    }

    $binary = @file_get_contents($file->getFileUri());
    if ($binary === FALSE || $binary === '') {
      $logger->error('Process @pid: unable to read file id @fid.', ['@pid' => $process_id, '@fid' => $fid]);
      return NULL;
    }

    return [
      'binary' => $binary,
      'mime' => (string) $file->getMimeType(),
    ];
  }

  /**
   * Detects the media type from content and checks it is allowed.
   *
   * @param array $media
   *   An array with binary and mime (a hint, used only if detection fails).
   * @param string[] $allowed_mime_prefixes
   *   The accepted media types, for example ['image/'].
   *
   * @return array|null
   *   The media with a detected mime type, or NULL if the type isn't allowed.
   */
  protected static function validateMedia(array $media, array $allowed_mime_prefixes) {
    $binary = $media['binary'];
    $mime = (string) ($media['mime'] ?? '');
    if (class_exists(\finfo::class)) {
      $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
      if ($detected && $detected !== 'application/octet-stream') {
        $mime = $detected;
      }
    }
    $image_info = @getimagesizefromstring($binary);
    if ($image_info && isset(self::ALLOWED_IMAGE_TYPES[$image_info[2]])) {
      $mime = image_type_to_mime_type($image_info[2]);
    }
    elseif (str_starts_with($mime, 'image/')) {
      // It claims to be an image, but isn't one we can parse and accept.
      $mime = '';
    }

    foreach ($allowed_mime_prefixes as $prefix) {
      if ($mime !== '' && str_starts_with($mime, $prefix)) {
        return ['binary' => $binary, 'mime' => $mime];
      }
    }
    \Drupal::logger('Maestro AI API')->error('Rejected media: its detected type "@mime" is not allowed here.', ['@mime' => $mime ?: 'unknown']);
    return NULL;
  }

}
