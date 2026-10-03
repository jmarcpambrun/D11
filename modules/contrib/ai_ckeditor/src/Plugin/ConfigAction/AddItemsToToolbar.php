<?php

declare(strict_types=1);

namespace Drupal\ai_ckeditor\Plugin\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action plugin to add multiple items to a CKEditor 5 toolbar.
 *
 * The core editor:addItemToToolbar config action can only add a single
 * toolbar item per recipe. This action accepts a list of items so a recipe
 * can add several buttons at once, for example both the AI Assistant
 * (aickeditor) and the AI Balloon Menu (ai_balloon_menu) buttons:
 *
 * @code
 * config:
 *   actions:
 *     editor.editor.basic_html:
 *       addItemsToToolbar:
 *         - aickeditor
 *         - ai_balloon_menu
 * @endcode
 *
 * This class deliberately has no ConfigAction attribute, so it is not
 * discovered as a plugin. Drupal core is expected to ship its own
 * editor:addItemsToToolbar config action, so this implementation is only
 * registered under that ID by ai_ckeditor_config_action_alter() when core
 * has not already defined it. Recipes can rely on addItemsToToolbar on any
 * supported core version and transparently switch to the core
 * implementation once it exists.
 *
 * @see https://www.drupal.org/i/3507570
 * @see \Drupal\ai_ckeditor\Hook\AiCKEditorHooks::configActionAlter()
 *
 * @internal
 *   This API is experimental. It can be removed once the core
 *   editor:addItemsToToolbar config action exists in all supported core
 *   versions.
 */
final class AddItemsToToolbar implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly CKEditor5PluginManagerInterface $pluginManager,
    private readonly string $pluginId,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get(CKEditor5PluginManagerInterface::class),
      $plugin_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    // Normalize $value, which could be one of three things:
    // - A string (add one item to the toolbar, no additional options)
    // - An associative array (add one item to the toolbar, with options)
    // - An indexed array (add multiple items to the toolbar, each of which
    //   could be one of the previous two forms)
    if (is_string($value) || (is_array($value) && !array_is_list($value))) {
      $value = [$value];
    }
    if (!is_array($value) || $value === []) {
      throw new ConfigActionException(sprintf('The %s config action requires one or more toolbar items.', $this->pluginId));
    }

    $editor = $this->configManager->loadConfigEntityByName($configName);
    assert($editor instanceof EditorInterface);

    if ($editor->getEditor() !== 'ckeditor5') {
      throw new ConfigActionException(sprintf('The %s config action only works with editors that use CKEditor 5.', $this->pluginId));
    }

    $changed = FALSE;
    foreach ($value as $item) {
      if (!is_string($item) && !is_array($item)) {
        throw new ConfigActionException(sprintf('The %s config action requires each toolbar item to be a string or an array of options.', $this->pluginId));
      }
      $changed = $this->applySingle($editor, $item) || $changed;
    }
    if ($changed) {
      $editor->save();
    }
  }

  /**
   * Adds an item to the toolbar.
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor to which the item should be added.
   * @param string|array $value
   *   The name of the item, or an array of options for the item.
   *
   * @return bool
   *   TRUE if the editor settings were changed, FALSE otherwise.
   */
  private function applySingle(EditorInterface $editor, string|array $value): bool {
    if (is_string($value)) {
      $value = ['item_name' => $value];
    }
    $item_name = $value['item_name'] ?? NULL;
    if (!is_string($item_name)) {
      throw new ConfigActionException(sprintf("The %s config action requires each toolbar item to define an 'item_name' string.", $this->pluginId));
    }

    $replace = $value['replace'] ?? FALSE;
    assert(is_bool($replace));

    $position = $value['position'] ?? NULL;

    $allow_duplicate = $value['allow_duplicate'] ?? FALSE;
    assert(is_bool($allow_duplicate));

    $editor_settings = $editor->getSettings();

    // If the item is already in the toolbar and we're not allowing duplicate
    // items, we're done.
    if (in_array($item_name, $editor_settings['toolbar']['items'], TRUE) && $allow_duplicate === FALSE && $item_name !== '|') {
      return FALSE;
    }

    if (is_int($position)) {
      // If we want to replace the item at this position, then `replace`
      // should be true. This would be useful if, for example, we wanted to
      // replace the Image button with the Media Library.
      array_splice($editor_settings['toolbar']['items'], $position, $replace ? 1 : 0, $item_name);
    }
    else {
      $editor_settings['toolbar']['items'][] = $item_name;
    }

    // If this item is associated with a plugin, ensure that it's configured
    // at the editor level, if necessary. A vertical separator is not
    // associated with any plugin.
    if ($item_name !== '|') {
      /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $definition */
      foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
        if (array_key_exists($item_name, $definition->getToolbarItems())) {
          // If plugin settings already exist, don't change them.
          if (array_key_exists($id, $editor_settings['plugins'])) {
            break;
          }
          elseif ($definition->isConfigurable()) {
            /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface $plugin */
            $plugin = $this->pluginManager->getPlugin($id, NULL);
            $editor_settings['plugins'][$id] = $plugin->defaultConfiguration();
          }
          // No need to examine any other plugins.
          break;
        }
      }
    }

    $editor->setSettings($editor_settings);
    return TRUE;
  }

}
