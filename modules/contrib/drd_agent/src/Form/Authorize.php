<?php

namespace Drupal\drd_agent\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\drd_agent\Setup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Authorize a new dashboard for this drd-agent.
 */
final class Authorize extends FormBase {

  /**
   * The setup service.
   *
   * @var \Drupal\drd_agent\Setup
   */
  protected Setup $setupService;

  /**
   * Authorize constructor.
   *
   * @param \Drupal\drd_agent\Setup $setup_service
   *   The setup service.
   */
  public function __construct(Setup $setup_service) {
    $this->setupService = $setup_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Authorize {
    return new static(
      $container->get('drd_agent.setup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drd_agent_authorize_form';
  }

  /**
   * Build the authorization form to paste the token from DRD.
   *
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The form.
   */
  protected function buildFormToken(array $form): array {
    $form['token'] = [
      '#type' => 'textarea',
      '#title' => t('Authentication token'),
      '#description' => t('Paste the token for this domain from the DRD dashboard, which you want to authorize.'),
      '#default_value' => '',
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Validate'),
    ];

    return $form;
  }

  /**
   * Build the authorization confirmation form.
   *
   * @param array $form
   *   The form array.
   *
   * @return array
   *   The form.
   */
  protected function buildFormConfirmation(array $form): array {
    if ($domain = $this->setupService->getDomain()) {
      $form['attention'] = [
        '#markup' => t('You are about to grant admin access to the Drupal Remote Dashboard on the following domain:'),
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
      $form['domain'] = [
        '#markup' => $domain,
        '#prefix' => '<div class="domain">',
        '#suffix' => '</div>',
      ];
      $form['cancel'] = [
        '#type' => 'submit',
        '#value' => t('Cancel'),
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t('Grant admin access'),
      ];
    }
    else {
      $session = $this->getRequest()->getSession();
      $session->remove('drd_agent_authorization_values');
      $form = $this->buildFormToken($form);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $session = $this->getRequest()->getSession();
    $form = !$session->has('drd_agent_authorization_values') ?
      $this->buildFormToken($form) :
      $this->buildFormConfirmation($form);

    $form['#attributes'] = [
      'class' => ['drd-agent-auth'],
    ];
    $form['#attached']['library'][] = 'drd_agent/general';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $session = $this->getRequest()->getSession();
    if (!$session->has('drd_agent_authorization_values')) {
      $session->set('drd_agent_authorization_values', $form_state->getValue('token'));
    }
    else {
      if ($form_state->getValue('op') === $form['submit']['#value']) {
        $values = $this->setupService->execute();
        $form_state->setResponse(new TrustedRedirectResponse($values['redirect']));
      }
      $session->remove('drd_agent_authorization_values');
    }
  }

}
