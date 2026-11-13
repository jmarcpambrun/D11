<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\ConfigTranslation\GroupRoleMapper;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Various core hook implementations for Group that do not belong elsewhere.
 */
final class CoreHooks {

  use StringTranslationTrait;

  public function __construct(
    protected GroupRelationTypeManagerInterface $groupRelationTypeManager,
    protected ConfigInstallerInterface $configInstaller,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Implements hook_rebuild().
   */
  #[Hook('rebuild')]
  public function rebuild(): void {
    $this->groupRelationTypeManager->installEnforced();
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {
    // Only create config objects while config import is not in progress.
    if (!$this->configInstaller->isSyncing()) {
      $this->groupRelationTypeManager->installEnforced();
    }
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    return match ($route_name) {
      'entity.group_type.content_plugins' => '<p>' . $this->t('Entities that can be added to this group type.') . '</p>',
      default => ''
    };
  }

  /**
   * Implements hook_config_translation_info_alter().
   */
  #[Hook('config_translation_info_alter')]
  public function configTranslationInfoAlter(array &$info): void {
    $info['group_role']['class'] = GroupRoleMapper::class;
  }

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    if (!$this->moduleHandler->moduleExists('views')) {
      return $requirements;
    }

    $views_ui_enabled = $this->moduleHandler->moduleExists('views_ui');

    // Find all view displays that still check for a group permission using the
    // deprecated 'group_permission' access plugin.
    // @todo Remove this in Group 5 along with the access plugin's option.
    $items = [];
    foreach ($this->entityTypeManager->getStorage('view')->loadMultiple() as $view) {
      assert($view instanceof ViewEntityInterface);

      foreach ($view->get('display') as $display_id => $display) {
        $access = $display['display_options']['access'] ?? [];
        if (($access['type'] ?? NULL) !== 'group_permission' || !isset($access['options']['group_permission'])) {
          continue;
        }

        $label = $this->t('@view (@display)', [
          '@view' => $view->label(),
          '@display' => $display['display_title'] ?? $display_id,
        ]);

        if ($views_ui_enabled) {
          $label = Link::fromTextAndUrl($label, Url::fromRoute('entity.view.edit_display_form', [
            'view' => $view->id(),
            'display_id' => $display_id,
          ]))->toRenderable();
        }

        $items[] = $label;
      }
    }

    if ($items) {
      $requirements['group_views_access_deprecated'] = [
        'title' => $this->t('Group: Views access by group permission'),
        'value' => $this->formatPlural(count($items), '1 view display uses a deprecated access setting', '@count view displays use a deprecated access setting'),
        'severity' => RequirementSeverity::Warning,
        'description' => [
          'intro' => [
            '#markup' => $this->t('The following view displays check for a group permission using the "Group permission" access plugin. This is deprecated and will be removed in Group 5. Use a Group contextual filter with the "Group permission" validator instead. Instructions can be found in the access settings of each affected display.'),
          ],
          'list' => [
            '#theme' => 'item_list',
            '#items' => $items,
          ],
        ],
      ];
    }

    return $requirements;
  }

}
