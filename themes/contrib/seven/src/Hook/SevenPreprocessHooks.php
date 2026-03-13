<?php

declare(strict_types=1);

namespace Drupal\seven\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Preprocess hook implementations.
 *
 * @internal
 *
 * @see https://www.drupal.org/node/3569107
 */
class SevenPreprocessHooks {

  public function __construct(
    protected readonly RequestStack $requestStack,
    protected readonly ThemeSettingsProvider $themeSettingsProvider,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for menu local tasks templates.
   *
   * Use preprocess hook to set #attached to child elements because they will be
   * processed by Twig, and \Drupal::service('renderer')->render() will be
   * invoked.
   */
  #[Hook("preprocess_menu_local_tasks")]
  public function menuLocalTasks(array &$variables): void {
    if (!empty($variables['primary'])) {
      $variables['primary']['#attached'] = [
        'library' => [
          'seven/drupal.nav-tabs',
        ],
      ];
    }
    elseif (!empty($variables['secondary'])) {
      $variables['secondary']['#attached'] = [
        'library' => [
          'seven/drupal.nav-tabs',
        ],
      ];
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for menu local task templates.
   */
  #[Hook("preprocess_menu_local_task")]
  public function menuLocalTask(array &$variables): void {
    $variables['attributes']['class'][] = 'tabs__tab';
  }

  /**
   * Implements hook_preprocess_HOOK() for node_add_list.
   */
  #[Hook("preprocess_node_add_list")]
  public function nodeAddList(array &$variables): void {
    if (!empty($variables['content'])) {
      /** @var \Drupal\node\NodeTypeInterface $type */
      foreach ($variables['content'] as $type) {
        $variables['types'][$type->id()]['label'] = $type->label();
        $variables['types'][$type->id()]['url'] = Url::fromRoute('node.add', ['node_type' => $type->id()])->toString();
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for block_content_add_list.
   *
   * Displays the list of available custom block types for creation, adding
   * separate variables for the label and the URL.
   */
  #[Hook("preprocess_block_content_add_list")]
  public function blockContentAddList(array &$variables): void {
    if (!empty($variables['content'])) {
      foreach ($variables['content'] as $type) {
        $variables['types'][$type->id()]['label'] = $type->label();
        $options = ['query' => $this->requestStack->getCurrentRequest()->query->all()];
        $variables['types'][$type->id()]['url'] = Url::fromRoute('block_content.add_form', ['block_content_type' => $type->id()], $options)->toString();
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for block templates.
   *
   * Disables contextual links for all but layout builder blocks.
   */
  #[Hook("preprocess_block")]
  public function block(array &$variables): void {
    $enable_contextual_links = $this->themeSettingsProvider->getSetting('enable_block_contextual_links');

    if (!$enable_contextual_links && isset($variables['title_suffix']['contextual_links']) && !isset($variables['elements']['#contextual_links']['layout_builder_block'])) {
      unset($variables['title_suffix']['contextual_links']);
      unset($variables['elements']['#contextual_links']);

      $variables['attributes']['class'] = array_diff($variables['attributes']['class'], ['contextual-region']);
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for admin_block_content.
   */
  #[Hook("preprocess_admin_block_content")]
  public function adminBlockContent(array &$variables): void {
    if (!empty($variables['content'])) {
      foreach ($variables['content'] as $key => $item) {
        $variables['content'][$key]['url'] = $item['url']->toString();
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for menu_local_action.
   */
  #[Hook("preprocess_menu_local_action")]
  public function menuLocalAction(array &$variables): void {
    $variables['link']['#options']['attributes']['class'][] = 'button--primary';
    $variables['link']['#options']['attributes']['class'][] = 'button--small';
    // We require the touchevents test for button styling.
    $variables['#attached']['library'][] = 'core/drupal.touchevents-test';
  }

  /**
   * Implements hook_preprocess_HOOK() for installation page templates.
   */
  #[Hook("preprocess_install_page")]
  public function installPage(array &$variables): void {
    $variables['#attached']['library'][] = 'seven/install-page';
  }

  /**
   * Implements hook_preprocess_HOOK() for maintenance page templates.
   */
  #[Hook("preprocess_maintenance_page")]
  public function maintenancePage(array &$variables): void {
    $variables['#attached']['library'][] = 'seven/maintenance-page';
  }

  /**
   * Implements hook_preprocess_HOOK() for views_view_fields.
   */
  #[Hook("preprocess_views_view_fields__media_library")]
  public function viewsViewFieldsMediaLibrary(array &$variables): void {
    // Add classes to media rendered entity field so it can be targeted for
    // styling. Adding this class in a template is very difficult to do.
    if (isset($variables['fields']['rendered_entity']->wrapper_attributes)) {
      $variables['fields']['rendered_entity']->wrapper_attributes->addClass('media-library-item__click-to-select-trigger');
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for item_list.
   *
   * This targets each new, unsaved, media item added to the media library
   * before they are saved.
   */
  #[Hook("preprocess_item_list__media_library_add_form_media_list")]
  public function itemListMediaLibraryAddFormMediaList(array &$variables): void {
    foreach ($variables['items'] as &$item) {
      $item['value']['preview']['#attributes']['class'][] = 'media-library-add-form__preview';
      $item['value']['fields']['#attributes']['class'][] = 'media-library-add-form__fields';
      $item['value']['remove_button']['#attributes']['class'][] = 'media-library-add-form__remove-button';

      // #source_field_name is set by AddFormBase::buildEntityFormElement()
      // to help themes and form_alter hooks identify the source field.
      $fields = &$item['value']['fields'];
      $source_field_name = $fields['#source_field_name'];
      if (isset($fields[$source_field_name])) {
        $fields[$source_field_name]['#attributes']['class'][] = 'media-library-add-form__source-field';
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for media_library_item.
   *
   * This targets each media item selected in an entity reference field.
   */
  #[Hook("preprocess_media_library_item__widget")]
  public function mediaLibraryItemWidget(array &$variables): void {
    $variables['content']['remove_button']['#attributes']['class'][] = 'media-library-item__remove';
  }

  /**
   * Implements hook_preprocess_HOOK() for media_library_item.
   *
   * This targets each pre-selected media item selected when adding new media in
   * the modal media library dialog.
   */
  #[Hook("preprocess_media_library_item__small")]
  public function mediaLibraryItemSmall(array &$variables): void {
    $variables['content']['select']['#attributes']['class'][] = 'media-library-item__click-to-select-checkbox';
  }

  /**
   * Implements hook_preprocess_HOOK() for links templates.
   *
   * This makes it so array keys of #links items are added as a class. This
   * functionality was removed in Drupal 8.1, but still necessary in some
   * instances.
   *
   * @todo Remove in https://drupal.org/node/3120962
   */
  #[Hook("preprocess_links")]
  public function links(array &$variables): void {
    if (!empty($variables['links'])) {
      foreach ($variables['links'] as $key => $value) {
        if (!is_numeric($key)) {
          $class = Html::getClass($key);
          $variables['links'][$key]['attributes']->addClass($class);
        }
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for links templates.
   *
   * This targets the menu of available media types in the media library's modal
   * dialog.
   *
   * @todo Do this in the relevant template once
   *   https://www.drupal.org/project/drupal/issues/3088856 is resolved.
   */
  #[Hook("preprocess_links__media_library_menu")]
  public function linksMediaLibraryMenu(array &$variables): void {
    foreach ($variables['links'] as &$link) {
      // Add a class to the Media Library menu items.
      $link['attributes']->addClass('media-library-menu__item');
      $link['link']['#options']['attributes']['class'][] = 'media-library-menu__link';
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for image widget templates.
   */
  #[Hook("preprocess_image_widget")]
  public function imageWidget(array &$variables): void {
    $data = &$variables['data'];

    // This prevents image widget templates from rendering preview container
    // HTML to users that do not have permission to access these previews.
    // @todo Revisit in https://drupal.org/node/953034
    // @todo Revisit in https://drupal.org/node/3114318
    if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
      unset($data['preview']);
    }

    // @todo Revisit everything in this conditional in
    //   https://drupal.org/node/3117430
    if (!empty($variables['element']['fids']['#value'])) {
      $file = reset($variables['element']['#files']);
      $data["file_{$file->id()}"]['filename']['#suffix'] = ' <span class="file-size">(' . ByteSizeMarkup::create($file->getSize()) . ')</span> ';
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for update_version.
   */
  #[Hook("preprocess_update_version")]
  public function updateVersion(array &$variables): void {
    $variables['#attached']['library'][] = 'seven/update-report';
  }

}
