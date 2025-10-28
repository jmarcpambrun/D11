<?php

namespace Drupal\schema_based_config_forms\FormElement;

use Drupal\Core\Language\LanguageInterface;
use Drupal\config_translation\FormElement\FormElementBase;

/**
 * Defines a tel element for the configuration translation interface.
 */
class Telephone extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getTranslationElement(LanguageInterface $translation_language, $source_config, $translation_config) {
    return [
      '#type' => 'tel',
    ] + parent::getTranslationElement($translation_language, $source_config, $translation_config);
  }

}
