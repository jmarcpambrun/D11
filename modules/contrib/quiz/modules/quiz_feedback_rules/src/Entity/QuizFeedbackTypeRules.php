<?php

namespace Drupal\quiz_feedback_rules\Entity;

use Drupal\quiz\Entity\QuizFeedbackType;
use Drupal\rules\Engine\RulesComponent;
use Drupal\rules\Ui\RulesUiComponentProviderInterface;

/**
 * Extends the default QuizFeedbackType.
 */
class QuizFeedbackTypeRules extends QuizFeedbackType implements RulesUiComponentProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getComponent(): RulesComponent {
    if (empty($this->component)) {
      // Provide a default for now.
      // @todo make expression configurable.
      $this->component = [
        'expression' => ['id' => 'rules_and'],
        'context_definitions' => [
          'quiz_result' => [
            'type' => 'entity:quiz_result',
            'label' => 'Quiz result',
            'description' => 'Quiz result to evaluate feedback',
          ],
        ],
      ];
    }

    if (!isset($this->componentObject)) {
      $this->componentObject = RulesComponent::createFromConfiguration($this->component);
    }
    return $this->componentObject;
  }

  /**
   * {@inheritdoc}
   */
  public function updateFromComponent(RulesComponent $component) {
    $this->component = $component->getConfiguration();
    $this->componentObject = $component;

    return $this;
  }

}
