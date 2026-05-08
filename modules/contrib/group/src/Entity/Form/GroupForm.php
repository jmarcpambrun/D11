<?php

namespace Drupal\group\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Form\CreateFormEnhancerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the group add and edit forms.
 *
 * @ingroup group
 */
class GroupForm extends ContentEntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $entity;

  /**
   * The private store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->privateTempStoreFactory = $container->get('tempstore.private');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    if ($this->operation !== 'add') {
      return $form;
    }

    $group = $this->getEntity();
    assert($group instanceof GroupInterface);

    $group_type = $group->getGroupType();
    if (!$group_type->creatorGetsMembership()) {
      return $form;
    }

    $this->getCreateFormEnhancer()->enhanceGroupForm($form, $form_state, ['group_roles' => $group_type->getCreatorRoleIds()]);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    $group_type = $this->entity->getGroupType();
    if ($this->operation === 'add') {
      $replace = ['@group_type' => $group_type->label()];

      if ($group_type->creatorGetsMembership()) {
        $actions['submit']['#value'] = $this->t('Create @group_type and become a member', $replace);
        $this->getCreateFormEnhancer()->enhanceGroupFormSubmit($actions['submit']);
      }
      else {
        $actions['submit']['#value'] = $this->t('Create @group_type', $replace);
      }
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // We call the parent function first so the entity is saved. We can then
    // read out its ID and redirect to the canonical route.
    $return = parent::save($form, $form_state);

    // Display success message.
    $t_args = [
      '@type' => $this->entity->getGroupType()->label(),
      '%title' => $this->entity->label(),
    ];

    $this->messenger()->addStatus($this->operation == 'edit'
      ? $this->t('@type %title has been updated.', $t_args)
      : $this->t('@type %title has been created.', $t_args)
    );

    $form_state->setRedirect('entity.group.canonical', ['group' => $this->entity->id()]);
    return $return;
  }

  /**
   * Gets the create form enhancer service.
   *
   * @return \Drupal\group\Form\CreateFormEnhancerInterface
   *   The create form enhancer service.
   */
  protected function getCreateFormEnhancer(): CreateFormEnhancerInterface {
    return \Drupal::service('group.create_form_enhancer');
  }

}
