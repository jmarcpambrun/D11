<?php

namespace Drupal\drd\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\ActionWidgetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for DRD actions.
 *
 * @package Drupal\drd\Form
 */
class Actions extends FormBase {

  /**
   * The action widget service.
   *
   * @var \Drupal\drd\ActionWidgetInterface
   */
  protected ActionWidgetInterface $actionWidget;

  /**
   * Actions constructor.
   *
   * @param \Drupal\drd\ActionWidgetInterface $actionWidget
   *   The action widget service.
   */
  final public function __construct(ActionWidgetInterface $actionWidget) {
    $this->actionWidget = $actionWidget;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Actions {
    return new static(
      $container->get('drd.action.widget')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drd_action_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->actionWidget->buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $this->actionWidget->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->actionWidget->submitForm($form, $form_state);
    if ($this->actionWidget->getExecutedCount()) {
      $action = $this->actionWidget->getSelectedAction();
      /** @var \Drupal\drd\Plugin\Action\Base $actionPlugin */
      $actionPlugin = $action->getPlugin();
      if ($actionPlugin->canBeQueued()) {
        $this->messenger()->addMessage(t('@action was queued.', [
          '@action' => $action->label(),
        ]));
      }
      else {
        $this->messenger()->addMessage(t('@action was executed.', [
          '@action' => $action->label(),
        ]));
      }
    }
  }

}
