<?php

declare(strict_types=1);

namespace Drupal\quiz\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Plugin attribute class for quiz questions.
 *
 * @see QuizQuestionPluginManager
 * @see plugin_api
 *
 * @ingroup quiz
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class QuizQuestion extends Plugin {

  /**
   * Constructs a QuizQuestion attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The label of the plugin.
   * @param array $handlers
   *   Array handlers for the question.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    protected readonly array $handlers = [],
    public readonly ?string $deriver = NULL,
  ) {
  }

}
