<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gitlab_api\Entity\GitlabProject;
use Drupal\gitlab_api\Entity\GitlabServer;
use Drupal\key\Entity\Key;

/**
 * Add/edit form for GitLab Project.
 */
final class GitlabProjectForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\gitlab_api\Entity\GitlabProjectInterface $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\\Drupal\\gitlab_api\\Entity\\GitlabProject::load',
      ],
      '#disabled' => !$entity->isNew(),
      '#description' => $this->t('Used in webhook URL: /gitlab-api/webhook/{this}.'),
    ];

    $server_options = [];
    foreach (GitlabServer::loadMultiple() as $id => $server) {
      $server_options[$id] = $server->label() ?: $server->getUrl();
    }
    $form['server'] = [
      '#type' => 'select',
      '#title' => $this->t('GitLab Server'),
      '#options' => $server_options,
      '#default_value' => $entity->getServerId(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];
    $form['gitlab_project_id'] = [
      '#type' => 'number',
      '#title' => $this->t('GitLab numeric project ID'),
      '#default_value' => $entity->getGitLabProjectId() ?: NULL,
      '#required' => TRUE,
      '#min' => 1,
    ];
    $form['gitlab_project_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GitLab project path'),
      '#description' => $this->t('e.g. mygroup/myproject'),
      '#default_value' => $entity->getGitLabProjectPath(),
      '#required' => TRUE,
    ];

    $key_options = [];
    foreach (Key::loadMultiple() as $id => $key) {
      $key_options[$id] = $key->label();
    }

    $form['access_token_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Access token source'),
      '#options' => [
        GitlabProject::TOKEN_SOURCE_SERVER => $this->t("Inherit from the server's authentication token"),
        GitlabProject::TOKEN_SOURCE_KEY => $this->t('Use a Key entity (per-project override)'),
      ],
      '#default_value' => $entity->getAccessTokenSource(),
      '#required' => TRUE,
    ];
    $form['access_token_key'] = [
      '#type' => 'select',
      '#title' => $this->t('Access token (Key entity)'),
      '#options' => $key_options,
      '#default_value' => $entity->getAccessTokenKeyId(),
      '#empty_option' => $this->t('- Select -'),
      '#description' => $this->t('A GitLab Personal or Project Access Token, stored in a Key entity.'),
      '#states' => [
        'visible' => [
          ':input[name="access_token_source"]' => ['value' => GitlabProject::TOKEN_SOURCE_KEY],
        ],
        'required' => [
          ':input[name="access_token_source"]' => ['value' => GitlabProject::TOKEN_SOURCE_KEY],
        ],
      ],
    ];

    $form['webhook_secret_keys'] = [
      '#type' => 'select',
      '#title' => $this->t('Webhook secret(s) (Key entities)'),
      '#options' => $key_options,
      '#multiple' => TRUE,
      '#default_value' => $entity->getWebhookSecretKeyIds(),
      '#required' => TRUE,
      '#description' => $this->t('Any selected secret will pass verification on inbound webhooks. Use multiple to support rotation.'),
    ];
    $form['maintainers'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maintainer usernames'),
      '#description' => $this->t('Comma-separated GitLab usernames, no @. Exposed in ECA actions as @-mentions.'),
      '#default_value' => implode(', ', $entity->getMaintainers()),
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $source = (string) $form_state->getValue('access_token_source');
    if ($source === GitlabProject::TOKEN_SOURCE_KEY && (string) $form_state->getValue('access_token_key') === '') {
      $form_state->setErrorByName('access_token_key', $this->t('Select a Key entity, or switch the source to inherit from the server.'));
    }

    // gitlab_project_id must be unique across all GitlabProject entities.
    $pid = (int) $form_state->getValue('gitlab_project_id');
    /** @var \Drupal\gitlab_api\Entity\GitlabProjectInterface[] $all */
    $all = $this->entityTypeManager->getStorage('gitlab_project')->loadMultiple();
    foreach ($all as $other) {
      if ($other->id() !== $form_state->getValue('id') && $other->getGitLabProjectId() === $pid) {
        $form_state->setErrorByName('gitlab_project_id', $this->t('Another GitLab Project (%label) already uses this GitLab project ID.', ['%label' => $other->label()]));
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    assert($entity instanceof GitlabProject);
    $values = $form_state->getValues();

    $maintainers = (string) ($values['maintainers'] ?? '');
    $values['maintainers'] = array_values(array_filter(array_map('trim', explode(',', $maintainers))));

    if (($values['access_token_source'] ?? '') === GitlabProject::TOKEN_SOURCE_SERVER) {
      $values['access_token_key'] = '';
    }

    foreach ($values as $key => $value) {
      $entity->set($key, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved GitLab Project %label.', ['%label' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
