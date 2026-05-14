<?php

namespace Drupal\gitlab_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gitlab_api\Entity\GitlabServer;
use Drupal\key\Entity\Key;

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

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $server->label() ?: $server->getUrl(),
      '#description' => $this->t('Human-readable label for this server.'),
      '#required' => TRUE,
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server URL with no trailing slash'),
      '#maxlength' => 255,
      '#default_value' => $server->getUrl(),
      '#description' => $this->t('The url of the gitlab instance (eg : <em>https://gitlab.com</em> or <em>https://gitlab.mysite.com</em>)'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $server->id(),
      '#machine_name' => [
        'source' => ['label'],
        'exists' => '\Drupal\gitlab_api\Entity\GitlabServer::load',
      ],
      '#disabled' => !$server->isNew(),
    ];

    $key_options = [];
    foreach (Key::loadMultiple() as $id => $key) {
      $key_options[$id] = $key->label();
    }
    $form['auth_token_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Authentication token (Key entity)'),
      '#options' => $key_options,
      '#default_value' => $server->getAuthTokenKeyId(),
      '#empty_option' => $this->t('- Select -'),
      '#description' => $this->t('A GitLab Personal or Project Access Token, stored in a Key entity. Used as the default token for any project that inherits the server token.'),
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
      if ($defaultServer->id() !== $this->entity->id()) {
        $defaultServer->setDefault(FALSE);
        $defaultServer->save();
      }
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
