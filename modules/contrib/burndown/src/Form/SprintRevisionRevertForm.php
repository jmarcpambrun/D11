<?php

namespace Drupal\burndown\Form;

use Drupal\burndown\Entity\SprintInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a Sprint revision.
 *
 * @ingroup burndown
 */
class SprintRevisionRevertForm extends ConfirmFormBase {

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
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->sprintStorage = $container->get('entity_type.manager')->getStorage('burndown_sprint');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'burndown_sprint_revision_revert_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to revert to the revision from %revision-date?', [
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
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
    return $this->t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
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
    // The revision timestamp will be updated when the revision is saved. Keep
    // the original one for the confirmation message.
    $original_revision_timestamp = $this->revision->getRevisionCreationTime();

    $this->revision = $this->prepareRevertedRevision($this->revision, $form_state);
    $this->revision->revision_log = $this->t('Copy of the revision from %date.', [
      '%date' => $this->dateFormatter->format($original_revision_timestamp),
    ]);
    $this->revision->save();

    $this->logger('content')
      ->notice('Sprint: reverted %title revision %revision.',
        [
          '%title' => $this->revision->label(),
          '%revision' => $this->revision->getRevisionId(),
        ]
      );
    $this->messenger()
      ->addMessage(
        $this->t('Sprint %title has been reverted to the revision from %revision-date.',
          [
            '%title' => $this->revision->label(),
            '%revision-date' => $this->dateFormatter->format($original_revision_timestamp),
          ]
        )
      );
    $form_state->setRedirect(
      'entity.burndown_sprint.version_history',
      ['burndown_sprint' => $this->revision->id()]
    );
  }

  /**
   * Prepares a revision to be reverted.
   *
   * @param \Drupal\burndown\Entity\SprintInterface $revision
   *   The revision to be reverted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\burndown\Entity\SprintInterface
   *   The prepared revision ready to be stored.
   */
  protected function prepareRevertedRevision(SprintInterface $revision, FormStateInterface $form_state) {
    $revision->setNewRevision();
    $revision->isDefaultRevision(TRUE);
    $revision->setRevisionCreationTime(\Drupal::time()->getRequestTime());

    return $revision;
  }

}
