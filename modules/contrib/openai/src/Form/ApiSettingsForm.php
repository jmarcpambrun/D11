<?php

namespace Drupal\openai\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure OpenAI client settings for this site.
 */
class ApiSettingsForm extends ConfigFormBase {
   /**
   * Settings form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
  * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler'),
    );
  }

 /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['openai.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
	  
	if ($this->moduleHandler->moduleExists('key')) {
      $form['api_key'] = [
        '#required' => TRUE,
        '#type' => 'key_select',
        '#title' => $this->t('API key'),
        '#default_value' => $this->config('openai.settings')->get('api_key'),
        '#description' => $this->t('The API key is required to interface with OpenAI services. Get your API key by signing up on the <a href=":link" target="_blank">OpenAI website</a>.',
          [
            ':link' => 'https://openai.com/api',
          ]
        ),
      ];
    }
    else {
      $form['api_key'] = [
        '#required' => TRUE,
        '#type' => 'textfield',
        '#title' => $this->t('API Key'),
        '#default_value' => $this->config('openai.settings')->get('api_key'),
        '#description' => $this->t('The API key is required to interface with OpenAI services. Get your API key by signing up on the <a href=":link" target="_blank">OpenAI website</a>.', [':link' => 'https://openai.com/api']),
        '#maxlength' => 256,
      ];
    }
	$form['api_org'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization ID'),
      '#default_value' => $this->config('openai.settings')->get('api_org'),
      '#description' => $this->t('The organization ID on your OpenAI account. This is required for some OpenAI services to work correctly.'),
    ];
	
    $form['message'] = [
      '#markup' => '<p>If you recently renewed or added more funds to OpenAI, please note that it can take a few hours for API access to be restored.</p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('openai.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_org', $form_state->getValue('api_org'))
      ->save();
	  
	\Drupal::service('page_cache_kill_switch')->trigger();
	  
    parent::submitForm($form, $form_state);
  }

}
