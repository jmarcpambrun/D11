<?php

declare(strict_types=1);

namespace Drupal\tfa\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Tfa attribute for plugin discovery.
 *
 * @ingroup tfa
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Tfa extends Plugin {

  /**
   * Constructs a Tfa attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the plugin.
   * @param string[] $helpLinks
   *   The helper metadata for setup plugin.
   * @param array{'saved': TranslatableMarkup, 'skipped': TranslatableMarkup}|array{} $setupMessages
   *   The messages to be displayed during setup steps.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly array $helpLinks = [],
    public readonly array $setupMessages = [],
    ?string $deriver = NULL,
  ) {
    parent::__construct($id, $deriver);
  }

}
