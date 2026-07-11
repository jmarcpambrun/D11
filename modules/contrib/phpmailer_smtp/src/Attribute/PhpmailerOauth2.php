<?php

declare(strict_types=1);

namespace Drupal\phpmailer_smtp\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a PhpmailerOauth2 attribute for plugin discovery.
 *
 * Plugin classes should use this attribute in preference to the
 * \Drupal\phpmailer_smtp\Annotation\PhpmailerOauth2 annotation, which is
 * retained only for backwards compatibility and will be removed in a
 * future major release.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PhpmailerOauth2 extends Plugin {

  /**
   * Constructs a PhpmailerOauth2 attribute.
   *
   * @param string $id
   *   The PHPMailer OAuth2 plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $name
   *   The human-readable name of the plugin.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $name,
    public readonly ?string $deriver = NULL,
  ) {}

}
