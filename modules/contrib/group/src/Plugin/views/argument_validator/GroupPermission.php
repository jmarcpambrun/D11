<?php

declare(strict_types=1);

namespace Drupal\group\Plugin\views\argument_validator;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Access\GroupPermissionHandlerInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Drupal\views\Attribute\ViewsArgumentValidator;
use Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase;

/**
 * Validates that the user has a group permission on the group argument.
 *
 * @ingroup views_argument_validate_plugins
 */
#[ViewsArgumentValidator(
  id: 'group_permission',
  title: new TranslatableMarkup('Group permission'),
)]
class GroupPermission extends ArgumentValidatorPluginBase implements CacheableDependencyInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected GroupPermissionHandlerInterface $permissionHandler,
    protected ModuleExtensionList $moduleExtensionList,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['group_types'] = ['default' => []];
    $options['group_permission'] = ['default' => 'view group'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $group_types = $this->entityTypeManager->getStorage('group_type')->loadMultiple();
    $group_types = array_map(fn(GroupTypeInterface $group_type) => Html::escape($group_type->label()), $group_types);
    $form['group_types'] = [
      '#type' => 'checkboxes',
      '#options' => $group_types,
      '#title' => $this->t('Restrict to the selected group types'),
      '#default_value' => $this->options['group_types'],
      '#description' => $this->t('If no group types are selected, groups from any type will be allowed.'),
    ];

    $permissions = [];
    foreach ($this->permissionHandler->getPermissions(TRUE) as $permission_name => $permission) {
      $display_name = $this->moduleExtensionList->getName($permission['provider']);
      $permissions[$display_name . ' : ' . $permission['section']][$permission_name] = strip_tags((string) $permission['title']);
    }
    $form['group_permission'] = [
      '#type' => 'select',
      '#options' => $permissions,
      '#title' => $this->t('Group permission'),
      '#default_value' => $this->options['group_permission'],
      '#description' => $this->t('Only users with the selected group permission will have this filter validate.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state, &$options = []) {
    $options['group_types'] = array_filter($options['group_types']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateArgument($arg): bool {
    if (!$arg) {
      return FALSE;
    }

    $group = $this->entityTypeManager->getStorage('group')->load($arg);
    if (!$group instanceof GroupInterface) {
      return FALSE;
    }

    if (!empty($this->options['group_types']) && !in_array($group->bundle(), $this->options['group_types'], TRUE)) {
      return FALSE;
    }

    return $group->hasPermission($this->options['group_permission'], $this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function getContextDefinition() {
    return EntityContextDefinition::fromEntityTypeId('group')
      ->setLabel($this->argument->adminLabel());
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $group_types = $this->entityTypeManager
      ->getStorage('group_type')
      ->loadMultiple(array_keys($this->options['group_types']));
    foreach ($group_types as $group_type) {
      $dependencies[$group_type->getConfigDependencyKey()][] = $group_type->getConfigDependencyName();
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['user.group_permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [];
  }

}
