<?php

namespace Drupal\drd\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\ContextProvider\RouteContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides form to configure entity related actions.
 *
 * @package Drupal\drd\Form
 */
class EntityActions extends Actions {

  /**
   * The route context object.
   *
   * @var \Drupal\drd\ContextProvider\RouteContext|null
   */
  private ?RouteContext $context = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): EntityActions {
    /** @var \Drupal\drd\Form\EntityActions $instance */
    $instance = parent::create($container);
    $instance->context = RouteContext::findDrdContext();
    if ($instance->context !== NULL && $instance->context->getViewMode()) {
      $instance->actionWidget->setMode($instance->context->getType());
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drd_entity_action_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if ($this->context !== NULL && $this->context->getViewMode()) {
      $form = parent::buildForm($form, $form_state);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->context !== NULL) {
      $this->actionWidget->setSelectedEntities($this->context->getEntity());
    }
    parent::submitForm($form, $form_state);
  }

}
