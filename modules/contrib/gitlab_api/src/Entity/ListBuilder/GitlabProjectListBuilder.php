<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Entity\ListBuilder;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * List builder for GitLab Project config entities.
 *
 * Redirects to the Servers collection when no server has been configured —
 * a project requires a server to function.
 */
final class GitlabProjectListBuilder extends ConfigEntityListBuilder implements EntityHandlerInterface {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly EntityStorageInterface $serverStorage,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
    $etm = $container->get('entity_type.manager');
    return new static(
      $entity_type,
      $etm->getStorage($entity_type->id()),
      $etm->getStorage('gitlab_server'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * When no server has been configured, redirects to the server collection
   * via EnforcedResponseException — render() itself never returns a Response,
   * which keeps the parent's array return type honest.
   */
  public function render() {
    $hasServers = (bool) $this->serverStorage->getQuery()->accessCheck(FALSE)->count()->execute();
    if (!$hasServers) {
      $this->messenger()->addStatus($this->t('Add a GitLab server first; projects are configured against a server.'));
      throw new EnforcedResponseException(
        new RedirectResponse(Url::fromRoute('entity.gitlab_server.collection')->toString()),
      );
    }
    return parent::render();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'label' => $this->t('Label'),
      'server' => $this->t('Server'),
      'path' => $this->t('GitLab path'),
      'status' => $this->t('Status'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\gitlab_api\Entity\GitlabProjectInterface $entity */
    return [
      'label' => $entity->label(),
      'server' => $entity->getServerId(),
      'path' => $entity->getGitLabProjectPath(),
      'status' => $entity->status() ? $this->t('Enabled') : $this->t('Disabled'),
    ] + parent::buildRow($entity);
  }

}
