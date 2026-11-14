<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\ECA\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;

/**
 * Condition: any AI response failure.
 */
#[EcaCondition(
  id: 'ai_eca_interceptor_response_failed',
  label: new TranslatableMarkup('AI response: failed (any exception)'),
  description: new TranslatableMarkup('True if the current event is an AI response failure, regardless of exception class.'),
  version_introduced: '2.0.0',
)]
class AiResponseFailedCondition extends AiEventConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    return $this->negationCheck($this->failedEvent() !== NULL);
  }

}
