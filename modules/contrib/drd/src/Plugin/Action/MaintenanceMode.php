<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;

/**
 * Provides a 'MaintenanceMode' action.
 *
 * @Action(
 *  id = "drd_action_maintenance_mode",
 *  label = @Translation("Maintenance Mode"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class MaintenanceMode extends BaseEntityRemote implements BaseConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  protected function setDefaultArguments(): void {
    parent::setDefaultArguments();
    $this->arguments['mode'] = 'getStatus';
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    $response = parent::executeAction($entity);
    if ($response !== FALSE) {
      if ($this->arguments['mode'] === 'getStatus') {
        $entity->cacheMaintenanceMode($response['data']);
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['drd_action_maintenance_mode'] = [
      '#type' => 'select',
      '#title' => t('Mode'),
      '#default_value' => 'getStatus',
      '#options' => [
        'getStatus' => t('get maintenance mode'),
        'on' => t('turn on maintenance mode'),
        'off' => t('turn off maintenance mode'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->arguments['mode'] = $form_state->getValue('drd_action_maintenance_mode');
  }

}
