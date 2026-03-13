<?php

namespace Drupal\gitlab_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gitlab_api\Entity\GitlabServer;

/**
 * Gitlab Server form.
 *
 * @property \Drupal\gitlab_api\Entity\GitlabServer $entity
 */
class GitlabServerForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $server = $this->entity;
    $form = parent::form($form, $form_state);

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server URL with no trailing slash'),
      '#maxlength' => 255,
      '#default_value' => $server->label(),
      '#description' => $this->t('The url of the gitlab instance (eg : <em>https://gitlab.com</em> or <em>https://gitlab.mysite.com</em>)'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $server->id(),
      '#machine_name' => [
        'source' => ['url'],
        'exists' => '\Drupal\gitlab_api\Entity\GitlabServer::load',
      ],
      '#disabled' => !$server->isNew(),
    ];

    $form['auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authentication token'),
      '#maxlength' => 255,
      '#default_value' => $server->getAuthToken(),
      '#description' => $this->t('Get this by creating a new personal access token on your gitlab profile (uri : <em>/profile/personal_access_tokens</em>)'),
      '#required' => TRUE,
    ];

    $form['default_server'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default server'),
      '#default_value' => $server->isDefault(),
      '#description' => $this->t('If there is already a default server, you can change it for this server by checking this box.'),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $server->isNew() || $server->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $server = $this->entity;

    if (!$form_state->getValue('default_server')) {
      $defaultServer = GitlabServer::loadDefaultServer();
      if (!$defaultServer || $defaultServer->id() === $server->id()) {
        $form_state->setError($form['default_server'], $this->t('You should have one default server.'));
      }
    }

    if (!filter_var($form_state->getValue('url'), FILTER_VALIDATE_URL)) {
      $form_state->setError($form['url'], $this->t('The server url seems not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function save(array $form, FormStateInterface $form_state): int {

    if ($this->entity->isDefault() && $defaultServer = GitlabServer::loadDefaultServer()) {
      $defaultServer->setDefault(FALSE);
      $defaultServer->save();
    }

    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result === SAVED_NEW
      ? $this->t('Created new gitlab server %label.', $message_args)
      : $this->t('Updated gitlab server %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
