<?php

namespace Drupal\quiz\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\UncacheableEntityAccessControlHandler;

/**
 * Determines access to QuizResult.
 *
 * @ingroup quiz
 */
class QuizResultAccessControlHandler extends UncacheableEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    $current_user = $this->prepareUser();
    if ($operation == 'view') {
      if ($current_user->hasPermission('view results for own quiz') && $account->id() == $entity->getQuiz()->get('uid')->getString()) {
        // User can view all quiz results for a quiz they authorized.
        return AccessResultAllowed::allowed();
      }
      if ($current_user->hasPermission('view own quiz_result') && $account->id() == $entity->get('uid')->getString()) {
        // User can view their own quiz result.
        return AccessResultAllowed::allowed();
      }
    }

    if ($operation == 'update') {
      if ($current_user->hasPermission('score own quiz') && $account->id() == $entity->getQuiz()->get('uid')->getString()) {
        // User can view all quiz results for a quiz they authored.
        return AccessResultAllowed::allowed();
      }
    }

    if (str_starts_with($operation, 'feedback_')) {
      // Access to feedback must be explicitly allowed.
      return AccessResultAllowed::neutral();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
