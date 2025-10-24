<?php

namespace Drupal\quiz\Entity;

/**
 * Empty object if result answer is broke.
 */
class QuizResultAnswerBroken extends QuizResultAnswer {

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function score(array $values): ?int {
    return NULL;
  }

}
