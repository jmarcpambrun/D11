<?php

namespace Drupal\burndown\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Swimlane edit forms.
 *
 * @ingroup burndown
 */
class SwimlaneForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\burndown\Entity\Swimlane $entity */
    $form = parent::buildForm($form, $form_state);

    // Hide miscellaneous items.
    $form['project']['#access'] = FALSE;
    $form['user_id']['#access'] = FALSE;
    $form['status']['#access'] = FALSE;
    $form['sort_order']['#access'] = FALSE;
    $form['show_backlog']['#access'] = FALSE;
    $form['show_project_board']['#access'] = FALSE;
    $form['show_completed']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Swimlane.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Swimlane.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.burndown_swimlane.canonical', ['burndown_swimlane' => $entity->id()]);
  }

}
