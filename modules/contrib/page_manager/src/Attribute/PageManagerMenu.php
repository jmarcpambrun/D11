<?php

declare(strict_types=1);

namespace Drupal\page_manager\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a PageManagerMenu attribute object.
 *
 * @see \Drupal\page_manager\Plugin\PageManagerMenuManager
 * @see \Drupal\page_manager\Plugin\PageManagerMenuInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class PageManagerMenu extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?string $deriver = NULL,
  ) {}

}
