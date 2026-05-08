<?php

namespace Drupal\group\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Access\GroupPermissionHandlerInterface;
use Drupal\group\Entity\GroupTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the user permissions administration form for a specific group type.
 */
class GroupPermissionsTypeSpecificForm extends GroupPermissionsForm {

  /**
   * The specific group type for this form.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    GroupPermissionHandlerInterface $permission_handler,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct($permission_handler, $module_handler);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('group.permissions'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getGroupType() {
    return $this->groupType;
  }

  /**
   * {@inheritdoc}
   */
  protected function getGroupRoles() {
    return $this->entityTypeManager
      ->getStorage('group_role')
      ->loadByProperties(['group_type' => $this->groupType->id()]);
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param ?\Drupal\group\Entity\GroupTypeInterface $group_type
   *   The group type used for this form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupTypeInterface $group_type = NULL) {
    // @todo Check for group type and throw exception.
    $this->groupType = $group_type;
    return parent::buildForm($form, $form_state);
  }

}
