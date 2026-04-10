<?php

namespace Drupal\quiz\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\quiz\Entity\QuizQuestionType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Quiz question authoring form.
 */
class QuizQuestionEntityForm extends ContentEntityForm {

  /**
   * Constructs a QuizQuestionEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    protected RequestStack $request_stack,
    protected AccountProxyInterface $currentUser,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity_manager = $this->entityTypeManager;
    $access_handler = $entity_manager->getAccessControlHandler('quiz');

    if ($qid = $this->request_stack->getCurrentRequest()->get('qid')) {
      // Requested addition to an existing quiz.
      $vid = $this->request_stack->getCurrentRequest()->get('vid');

      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $entity_manager->getStorage('quiz');
      $quiz = $storage->loadRevision($vid);

      // Check if the user can add a question to the requested quiz.
      if ($access_handler->access($quiz, 'update')) {
        $form['quiz_id'] = [
          '#title' => $this->t('Quiz ID'),
          '#type' => 'value',
          '#value' => $qid,
        ];

        $form['quiz_vid'] = [
          '#title' => $this->t('Quiz revision ID'),
          '#type' => 'value',
          '#value' => $vid,
        ];
      }
    }

    if ($this->entity->hasBeenAnswered()) {
      $override = $this->currentUser->hasPermission('override quiz revisioning');
      if ($this->configFactory()->get('quiz.settings')->get('revisioning')) {
        $form['revision']['#required'] = !$override;
      }
      else {
        $message = $override ?
          $this->t('<strong>Warning:</strong> This question has attempts. You can edit this question, but it is not recommended.<br/>Attempts in progress and reporting will be affected.<br/>You should delete all attempts on this question before editing.') :
          $this->t('You must delete all attempts on this question before editing.');
        // Revisioning is disabled.
        $form['revision_information']['#access'] = FALSE;
        $form['revision']['#access'] = FALSE;
        $form['actions']['warning'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $message,
        ];
        $this->messenger()->addWarning($message);
        $form['actions']['#disabled'] = TRUE;
      }
      $form['revision']['#description'] = '<strong>Warning:</strong> This question has attempts.<br/>In order to update this question you must create a new revision.<br/>This will affect reporting.<br/>You must update the quizzes with the new revision of this question.';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Redirect to questions form after quiz creation.
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $quiz_question = $this->entity;
    $insert = $quiz_question->isNew();

    parent::save($form, $form_state);

    if ($form_state->getValue('quiz_id')) {
      // Add to quiz if coming from the questions form.
      $vid = $form_state->getValue('quiz_vid');

      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('quiz');
      /** @var \Drupal\quiz\Entity\Quiz $quiz */
      $quiz = $storage->loadRevision($vid);
      $quiz->addQuestion($this->entity);
    }

    $type = QuizQuestionType::load($quiz_question->bundle());
    $t_args = ['@type' => $type->label(), '%title' => $quiz_question->toLink()->toString()];

    if ($insert) {
      $this->messenger()->addStatus($this->t('@type %title has been created.', $t_args));
    }
    else {
      $this->messenger()->addStatus($this->t('@type %title has been updated.', $t_args));
    }

    if ($qid = $form_state->getValue('quiz_id')) {
      $form_state->setRedirect('quiz.questions', ['quiz' => $qid]);
    }
  }

}
