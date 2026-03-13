<?php

namespace Drupal\maestro;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\maestro\MaestroSetProcessVariablePluginInterface;

/**
 * Maestro Set Process Variable Task Plugin Base.
 *
 * Provides a base class for Maestro Set Process Variable plugins.  
 * 
 * @MaestroSetProcessVariablePlugin(
 *   id = "a unique ID for your Maestro Set Process Variable Plugin",
 *   short_description = @Translation("A short description"),
 *   description = @Translation("Description"),
 * )
 */
abstract class MaestroSetProcessVariablePluginBase extends PluginBase implements MaestroSetProcessVariablePluginInterface {
  use StringTranslationTrait; // Allow the abilty to use $this->t() for translations.
  
  /**
   * task  
   *   The task object. This will be the running instance of the MaestroSetProcessVariableTask object.  
   *
   * @var Drupal\maestro\Plugin\EngineTasks\MaestroSetProcessVariableTask
   */
  protected $task = NULL;
  
  /**
   * templateMachineName  
   *   The string machine name of the Maestro template.  
   *
   * @var string
   */
  protected $templateMachineName = '';
  
  /**
   * queueID  
   *   The queueID of the executing task.
   *
   * @var string
   */
  protected $queueID = '';

  /**
   * processID  
   *   The processID of the executing task.
   *
   * @var string
   */
  protected $processID = '';
    
  /**
   * form_state  
   *   In the event we are editing a task, the form state is made available
   *
   * @var FormStateInterface
   */
  protected $form_state = NULL;
  
  /**
   * form  
   *   In the event we are editing a task, the form array is made available
   *
   * @var array
   */
  protected $form = NULL;
      
  /**
   * Constructor.
   */
  public function __construct($configuration = NULL) {
    if (is_array($configuration)) {
      $this->task = $configuration['task'] ?? NULL;
      $this->templateMachineName = $configuration['templateMachineName'] ?? NULL;
      $this->queueID = $configuration['queueID'] ?? NULL;
      $this->processID = $configuration['processID'] ?? NULL;
      $this->form_state = $configuration['form_state'] ?? NULL;
      $this->form = $configuration['form'] ?? NULL;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getPluginId() {
    return parent::getPluginId();
  }
   
  /**
   * {@inheritDoc}
   */
  public function getSPVTaskConfigFormElements() : array {
    return [
      '#markup' => $this->t('This Process Variable Plugin does not have any further configuration options.'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function validateSPVTaskEditForm(array &$form, FormStateInterface $form_state) : void {
    // Implement validateMaestroAiTaskEditForm() method.
  }

  /**
   * {@inheritDoc}
   */
  public function execute() : ?string {
    // Implement your specific SPV Task call.  You must return a string.
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    // Implement prepareTaskForSave() method.
  }

  /**
   * {@inheritDoc}
   */
  public function performSPVTaskValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) : void {
    // Implement performSPVTaskValidityCheck() method.
  }

  
}