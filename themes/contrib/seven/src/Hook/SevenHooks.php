<?php

declare(strict_types=1);

namespace Drupal\seven\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\views\ViewExecutable;

/**
 * Various hook implementations.
 *
 * @internal
 *
 * @see https://www.drupal.org/node/3569107
 */
class SevenHooks {

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook("element_info_alter")]
  public function elementInfoAlter(array &$type): void {
    // We require the touchevents test for button styling.
    if (isset($type['button'])) {
      $type['button']['#attached']['library'][] = 'core/drupal.touchevents-test';
    }
  }

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook("views_pre_render")]
  public function viewsPreRender(ViewExecutable $view): void {
    $add_classes = function (&$option, array $classes_to_add) {
      $classes = preg_split('/\s+/', $option);
      $classes = array_filter($classes);
      $classes = array_merge($classes, $classes_to_add);
      $option = implode(' ', array_unique($classes));
    };

    if ($view->id() === 'media_library') {
      if ($view->display_handler->options['defaults']['css_class']) {
        $add_classes($view->displayHandlers->get('default')->options['css_class'], ['media-library-view']);
      }
      else {
        $add_classes($view->display_handler->options['css_class'], ['media-library-view']);
      }

      if ($view->current_display === 'page') {
        if (array_key_exists('media_bulk_form', $view->field)) {
          $add_classes($view->field['media_bulk_form']->options['element_class'], ['media-library-item__click-to-select-checkbox']);
        }
        if (array_key_exists('rendered_entity', $view->field)) {
          $add_classes($view->field['rendered_entity']->options['element_class'], ['media-library-item__content']);
        }
        if (array_key_exists('edit_media', $view->field)) {
          $add_classes($view->field['edit_media']->options['alter']['link_class'], ['media-library-item__edit']);
        }
        if (array_key_exists('delete_media', $view->field)) {
          $add_classes($view->field['delete_media']->options['alter']['link_class'], ['media-library-item__remove']);
        }
      }
      elseif (str_starts_with($view->current_display, 'widget')) {
        if (array_key_exists('rendered_entity', $view->field)) {
          $add_classes($view->field['rendered_entity']->options['element_class'], ['media-library-item__content']);
        }
        if (array_key_exists('media_library_select_form', $view->field)) {
          $add_classes($view->field['media_library_select_form']->options['element_wrapper_class'], ['media-library-item__click-to-select-checkbox']);
        }

        if ($view->display_handler->options['defaults']['css_class']) {
          $add_classes($view->displayHandlers->get('default')->options['css_class'], ['media-library-view--widget']);
        }
        else {
          $add_classes($view->display_handler->options['css_class'], ['media-library-view--widget']);
        }
      }
    }
  }

}
