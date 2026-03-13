<?php

namespace Drupal\burndown\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Sprint revision.
 *
 * @ingroup burndown
 */
class SprintRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Sprint revision.
   *
   * @var \Drupal\burndown\Entity\SprintInterface
   */
  protected $revision;

  /**
   * The Sprint storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $sprintStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->sprintStorage = $container->get('entity_type.manager')->getStorage('burndown_sprint');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'burndown_sprint_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.burndown_sprint.version_history', ['burndown_sprint' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $burndown_sprint_revision = NULL) {
    $this->revision = $this->SprintStorage->loadRevision($burndown_sprint_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->SprintStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')
      ->notice('Sprint: deleted %title revision %revision.',
        [
          '%title' => $this->revision->label(),
          '%revision' => $this->revision->getRevisionId(),
        ]
      );
    $this->messenger()
      ->addMessage(
        $this->t('Revision from %revision-date of Sprint %title has been deleted.',
          [
            '%revision-date' => \Drupal::service('date.formatter')->format($this->revision->getRevisionCreationTime()),
            '%title' => $this->revision->label(),
          ]
        )
      );
    $form_state->setRedirect(
      'entity.burndown_sprint.canonical',
       ['burndown_sprint' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {burndown_sprint_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.burndown_sprint.version_history',
         ['burndown_sprint' => $this->revision->id()]
      );
    }
  }

}
