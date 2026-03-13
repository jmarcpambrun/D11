<?php

namespace Drupal\quiz\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\UncacheableEntityAccessControlHandler;
use Drupal\quiz\Services\QuizSessionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Determines access to QuizResultAnswer.
 *
 * @ingroup quiz
 */
class QuizResultAnswerAccessControlHandler extends UncacheableEntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Constructs a NodeAccessControlHandler object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\quiz\Services\QuizSessionInterface $quizSession
   *   The quiz session service.
   */
  public function __construct(EntityTypeInterface $entity_type, protected QuizSessionInterface $quizSession) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('quiz.session'),
    );
  }

  /**
   * Control access to taking a question or viewing feedback within a quiz.
   *
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'take') {
      /** @var \Drupal\quiz\Entity\QuizResultAnswer $entity */
      $quiz = $entity->getQuizResult()->getQuiz();
      $quiz_session = $this->quizSession;
      if (!$quiz_session->isTakingQuiz($quiz)) {
        // No access if the user isn't taking this quiz.
        return AccessResultForbidden::forbidden();
      }

      if ($quiz->get('allow_jumping')->getString()) {
        // Access to go to any question. Yay.
        return AccessResultAllowed::allowed();
      }

      $qra_last = $entity->getPrevious();

      if (!$quiz->get('backwards_navigation')->getString()) {
        // No backwards navigation.
        if ($entity->isAnswered()) {
          // This question was answered already.
          return AccessResultForbidden::forbidden();
        }
      }

      // Enforce normal navigation.
      if (!$qra_last || $qra_last->isAnswered()) {
        // Previous answer was submitted or this is the first question.
        return AccessResultAllowed::allowed();
      }

      return AccessResultForbidden::forbidden();
    }

    if ($operation == 'feedback') {
      if ($entity->isAnswered()) {
        // The user has answered this question, so they can see the feedback.
        return AccessResultAllowed::allowed();
      }

      // If they haven't answered the question, we want to make sure feedback is
      // blocked as it could be exposing correct answers.
      // @todo We may also want to check if they are viewing feedback for the
      // current question.
      return AccessResultForbidden::forbidden();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
