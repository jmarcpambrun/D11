<?php

namespace Drupal\maestro_ai_task\MaestroAiTaskAPI;

use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro_ai_task\Entity\MaestroAIStorage;

/**
 * MaestroAiTaskAPI API class.
 *
 * Provides the Maestro AI Task API.
 */
class MaestroAiTaskAPI {
  
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
    // Get the extension and remove the preamble (if it exists)
    if (preg_match('/^data:image\/(\w+);base64,/', $image_string, $matches)) {
      // Extract format (e.g., png, jpeg, webp)
      $extension = $matches[1];
      $mime_type = 'image/' . $extension;
      $image_string = substr($image_string, strpos($image_string, ',') + 1);
    }
    $image_data = base64_decode($image_string);
    if (!$file_path_base) {
      // We use a predefined private files path.
      // If this fails, good chance that the private files path doesn't exist.
      if(empty($unique_id)) {
        $unique_id = 'no-unique-id';
      }
      if(empty($process_id)) {
        $process_id = 'no-process-id';
      }
      $file_path = 'private://ai-image-' . $unique_id . '-' . $process_id . '.' . $extension;
      $file_path = \Drupal::service('file_system')->realpath($file_path);
    }
    else {
      $file_path = $file_path_base;
    }

    // Suppress the file_put_contents warning when the file location
    // can't be found.
    // We handle this as a blank array return and avoids warnings showing.
    if (@file_put_contents($file_path, $image_data)) {
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

}
