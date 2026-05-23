<?php

namespace Drupal\ai_media_image\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Configure AI Media Image module.
 */
class AiMediaImageSettingsForm extends ConfigFormBase {

  use StringTranslationTrait;
  use RedundantEditableConfigNamesTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_media_image_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['provider_configuration_open'] = [
      '#title' => $this->t("Open 'Provider Configuration' by default"),
      '#type' => 'checkbox',
      '#config_target' => 'ai_media_image.settings:provider_configuration_open',
      '#description' => $this->t('If the Provider Configuration fieldset should be open by default, or not.'),
    ];
    return parent::buildForm($form, $form_state);
  }

}
