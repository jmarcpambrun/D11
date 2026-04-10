<?php

namespace Drupal\drd_agent\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure drd-agent settings for this site.
 */
final class Settings extends FormBase {

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drd_agent_settings_form';
  }

  /**
   * Constructs settings.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state factory.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Settings {
    return new Settings(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => t('Debug mode'),
      '#default_value' => $this->state->get('drd_agent.debug_mode', FALSE),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $debug_mode = $form_state->getValue('debug_mode');
    $this->state->set('drd_agent.debug_mode', $debug_mode);
    $this->messenger()->addStatus($this->t('Settings saved'));
  }

}
