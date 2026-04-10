<?php

namespace Drupal\drd_install_core\EventSubscriber;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\drd\Entity\Core;
use Drupal\gitlab_api\Event\CreateProject as CreateProjectEvent;
use Drupal\gitlab_api\GitLabEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * CreateProject event subscriber.
 */
class CreateProject implements EventSubscriberInterface {

  /**
   * Gets called when a GitLab project was created.
   *
   * @param \Drupal\gitlab_api\Event\CreateProject $event
   *   The GitLab create project event.
   */
  public function onCreateProject(CreateProjectEvent $event): void {
    $webform_submission = $event->getWebformSubmission();
    $project = $event->getProject();
    $data = $webform_submission->getData();

    foreach ($webform_submission->getWebform()->getHandlers('drd_core') as $handler) {
      $config = $handler->getConfiguration();
      $core_id = $data[($config['settings']['field_core_id'])];
      if ($core = Core::load($core_id)) {
        $core->setGitRepo($project['ssh_url_to_repo']);
        try {
          $core->save();
        }
        catch (EntityStorageException) {
          // @todo Handle exception.
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      GitLabEvents::CREATEPROJECT => ['onCreateProject'],
    ];
  }

}
