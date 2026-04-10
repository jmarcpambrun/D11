<?php

namespace Drupal\maestro_ai_task;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Executable\ExecutableInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * The Maestro AI Task Capabilities Interface.  
 * 
 * This interface defines the required methods for Maestro AI Task Capabilities.  
 */
interface MaestroAiTaskCapabilitiesInterface extends ExecutableInterface, PluginInspectionInterface {

  /**
   * Gets the Maestro AI Task configuration form.  
   * This method is used during the edit of a Maestro AI Task in order to add form elements to the task edit form.  
   *
   * @return array  
   *   Must return an array of form fields (or an empty array if nothing to add).  
   *
   * @param array $task  
   *   The fully loaded Maestro Task array.  
   * @param string $templateMachineName  
   *   The Maestro template machine name  
   */
  public function getMaestroAiTaskConfigFormElements() : array;
  
  /**
   * execute 
   *  This method is used to execute the AI Task call for the specific implementation of this AI Capability.  
   *
   * @param  string $prompt  
   *   The prompt from the task  
   * @param  Drupal\maestro_ai_task\Plugin\EngineTasks\MaestroAITask $task  
   *   The task object. This will be the running instance of the MaestroAITask object.  
   * @return string  
   *   Returns the result of the AI Task call.  
   */
  public function execute() : ?string;

  /**
   * This method must be called by the template builder in order to validate the form entry values before saving.
   * Set any form errors using $form_state->setErrorByName('form_element_name', 'Error message');
   */
  public function validateMaestroAiTaskEditForm(array &$form, FormStateInterface $form_state) : void;

  /**
   * The Maestro AI Task capabilites setup for task save. Populate the appropriate task elements.
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
   * Lets the AI Capability perform validation on itself.  
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
  public function performMaestroAiTaskValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) : void;

  
  /**
   * allowConfigurableReturnFormat  
   *   Signals whether this Maestro AI Task Capability allows the usage of the "Return format from the AI Call" option.  
   *   Setting is 'ai_return_format' and is a form field provided in the MaestroAiTask plugin.
   *
   * @return bool  
   *   Returning TRUE will display the Return Format field and will be appended to the prompt during execution.  
   *   FALSE hides it and removes from executed prompt.
   */
  public function allowConfigurableReturnFormat() : bool;

}