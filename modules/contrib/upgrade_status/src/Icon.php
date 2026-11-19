<?php

namespace Drupal\upgrade_status;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;

/**
 * Renders the Phosphor icons that ship with the module.
 *
 * The SVG files are in icons/phosphor, from @phosphor-icons/core (MIT). Icons
 * up to 18 pixels use the bold weight, larger ones the regular weight. The
 * SVG is inlined, so it takes the text color with currentColor. The core Icon
 * API is not used, because it needs Drupal 11.1.
 */
final class Icon {

  /**
   * The SVG files read so far, keyed by the path.
   *
   * @var string[]
   */
  private static array $files = [];

  /**
   * Renders an icon.
   *
   * @param string $name
   *   The Phosphor name, like 'x-circle'.
   * @param int $size
   *   The width and height in pixels.
   * @param string|\Stringable|null $label
   *   The accessible name if the icon has a meaning on its own, or NULL for
   *   a decorative icon, which is hidden from assistive technology.
   * @param string $class
   *   Additional classes.
   *
   * @return \Drupal\Core\Render\Markup
   *   The SVG element.
   */
  public static function render(string $name, int $size = 16, string|\Stringable|null $label = NULL, string $class = ''): Markup {
    $directory = dirname(__DIR__) . '/icons/phosphor/';
    $path = $directory . 'bold/' . $name . '.svg';
    if ($size > 18 && file_exists($directory . 'regular/' . $name . '.svg')) {
      $path = $directory . 'regular/' . $name . '.svg';
    }
    self::$files[$path] ??= (string) file_get_contents($path);

    $attributes = sprintf('class="us-icon %s" width="%d" height="%d" focusable="false"', Html::escape(trim($class)), $size, $size);
    $attributes .= $label === NULL ? ' aria-hidden="true"' : sprintf(' role="img" aria-label="%s"', Html::escape((string) $label));
    return Markup::create(preg_replace('/^<svg /', '<svg ' . $attributes . ' ', trim(self::$files[$path]), 1));
  }

}
