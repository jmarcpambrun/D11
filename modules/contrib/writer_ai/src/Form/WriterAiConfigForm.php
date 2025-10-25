<?php

declare(strict_types=1);

namespace Drupal\writer_ai\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Writer AI Provider settings.
 */
final class WriterAiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'writer_ai.settings';

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new WriterAiConfigForm object.
   */
  final public function __construct(AiProviderPluginManager $ai_provider_manager, ModuleHandlerInterface $module_handler) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'writer_ai_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['writer_ai.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Writer API Key'),
      '#description' => $this->t('The Writer API Key.'),
      '#default_value' => $config->get('api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Do a check if its getting setup or disabled.
    if ($form_state->getValue('api_key')) {
      // Here we set the default providers per the operation for our provider.
      $this->aiProviderManager->defaultIfNone('chat', 'writerai', 'palmyra-x5');
    }
    else {
      // We notify 3rd party modules that it has been disabled.
      $this->aiProviderManager->providerDisabled('writerai');
    }

    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
