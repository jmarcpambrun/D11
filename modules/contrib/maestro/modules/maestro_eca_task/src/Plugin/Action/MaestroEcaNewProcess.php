<?php

namespace Drupal\maestro_eca_task\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\maestro\Engine\MaestroEngine;

/**
 * Spawn Maestro process action.
 *
 * @Action(
 *   id = "maestro_eca_spawn_process",
 *   label = @Translation("Maestro: Spawn New Process"),
 *   description = @Translation("Spawns a new Maestro process and passes the entity to the Maestro Entity Identifiers referenced with the configured ID."),
 *   type = "entity"
 * )
 */
class MaestroEcaNewProcess extends ConfigurableActionBase {

  /**
   * The instantiated entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity;


  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    $template = $this->configuration['maestro_eca_spawn_template'];
    $entity_identifier = $this->configuration['maestro_eca_entity_identifier'] ?? NULL;
    $process_id_token = $this->configuration['maestro_eca_process_id_token'] ?? NULL;

    $maestro_engine = new MaestroEngine();
    // Need this returned back into ECA?  We can set a token.
    $process_id = $maestro_engine->newProcess($template);
    if ($process_id) {
      // Register the token into ECA's token context.
      if(!empty($process_id_token)) {
        $this->tokenService->addTokenData('maestro_process_id', $process_id);  
      }
      
      // If there is an entity identifier configured, we need to create the entity identifier in Maestro.
      if (!empty($entity_identifier)) {
        MaestroEngine::createEntityIdentifier($process_id, $entity->getEntityTypeId(), $entity->bundle(), $entity_identifier, $entity->id());
      }
    }
    else {
      \Drupal::messenger()->addError($this->t('Unable to begin workflow.  Please check with administrator for more information.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['maestro_eca_spawn_template'] = '';
    $config['maestro_eca_entity_identifier'] = '';
    $config['maestro_eca_process_id_token'] = '';
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $configured_templates = MaestroEngine::getTemplates();

    $templates = ['' => $this->t('Choose a template')];
    foreach($configured_templates as $key => $template) {
      $templates[$key] = $template->get('label');
    }
    $form['maestro_eca_spawn_template'] = [
      '#type' => 'select',
      '#title' => $this->t('Spawn with Maestro Template'),
      '#description' => $this->t('Select the Maestro template to spawn a new process with.'),
      '#default_value' => $this->configuration['maestro_eca_spawn_template'],
      '#options' => $templates,
      '#required' => TRUE,
    ];

    // Potentially there isn't a need to set an entity identifier.  So we'll leave this as optional.
    $form['maestro_eca_entity_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maestro Entity Identifier'),
      '#description' => $this->t('Optional. If there is an entity, it will be linked to the Maestro Process Entity Identifer under this ID. If you leave this blank, no Maestro Entity Identifer will be created.'),
      '#default_value' => $this->configuration['maestro_eca_entity_identifier'],
      '#required' => FALSE,
    ];

    $form['maestro_eca_process_id_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maestro Process ID ECA Token Name'),
      '#description' => $this->t('Optional. What do you want the ECA token name to be that will store the Maestro Process ID?'),
      '#default_value' => $this->configuration['maestro_eca_process_id_token'],
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['maestro_eca_spawn_template'] = $form_state->getValue('maestro_eca_spawn_template');
    $this->configuration['maestro_eca_entity_identifier'] = $form_state->getValue('maestro_eca_entity_identifier');
    $this->configuration['maestro_eca_process_id_token'] = $form_state->getValue('maestro_eca_process_id_token');
    
    parent::submitConfigurationForm($form, $form_state);
  }

}
