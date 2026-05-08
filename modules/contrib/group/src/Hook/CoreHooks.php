<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\ConfigTranslation\GroupRoleMapper;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;

/**
 * Various core hook implementations for Group that do not belong elsewhere.
 */
final class CoreHooks {

  use StringTranslationTrait;

  public function __construct(
    protected GroupRelationTypeManagerInterface $groupRelationTypeManager,
    protected ConfigInstallerInterface $configInstaller,
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

}
