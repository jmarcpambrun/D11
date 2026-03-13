<?php

namespace Drupal\maestro;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Executable\ExecutableInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The Maestro Set Process Variable Plugin Interface.
 */
interface MaestroSetProcessVariablePluginInterface extends ExecutableInterface, PluginInspectionInterface {
/**
   * Gets the Maestro Set Process Variable Task configuration form.  
   * This method is used during the edit of a Maestro Set Process Variable Task in order to add form elements to the task edit form.  
   *
   * @return array  
   *   Must return an array of form fields (or an empty array if nothing to add).  
   *
   * @param array $task  
   *   The fully loaded Maestro Task array.  
   * @param string $templateMachineName  
   *   The Maestro template machine name  
   */
  public function getSPVTaskConfigFormElements() : array;
  
  /**
   * execute 
   *  This method is used to execute the Set Process Variable Task call for the specific implementation of this SPV Plugin.  
   *
   * @param  string $prompt  
   *   The prompt from the task  
   * @param  Drupal\maestro\Plugin\EngineTasks\MaestroSetProcessVariableTask $task  
   *   The task object. This will be the running instance of the MaestroSetProcessVariableTask object.  
   * @return string  
   *   Returns the result of the SPV Task call.  
   */
  public function execute() : ?string;

  /**
   * This method must be called by the template builder in order to validate the form entry values before saving.
   * Set any form errors using $form_state->setErrorByName('form_element_name', 'Error message');
   */
  public function validateSPVTaskEditForm(array &$form, FormStateInterface $form_state) : void;

  /**
   * The Maestro SPV Task setup for task save. Populate the appropriate task elements.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form's form state.
   * @param array $task
   *   The fully loaded task array from the template.
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void;

  /**
   * Lets the SPV Plugin perform validation on itself.  
   * 
   * Return array MUST be in the format of array(  
   *   'taskID' =>  the task machine name,  
   *   'taskLabel' => the human readable label for the task,  
   *   'reason' => the reason for the failure  
   *   )  
   *
   * @param array $validation_failure_tasks  
   *   The array of other validation failures. Supplement any failures in validation by adding to THIS array.  
   * @param array $validation_information_tasks Supplement any warnings or information in validation by adding to THIS array.  
   *   The array of informational messages.  
   * @param array $task  
   *   The passed-in fully-loaded task from the template (array)  
   */
  public function performSPVTaskValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) : void;

}
