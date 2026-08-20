<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\locale\LocaleConfigBatch;
use Drupal\locale\LocaleFetch;
use Drupal\locale\LocaleSource;

/**
 * Provides a 'UpdateTranslations' code.
 */
class UpdateTranslations extends Base {

  /**
   * {@inheritdoc}
   */
  public function execute(): array {
    if ($this->moduleHandler->moduleExists('locale')) {
      $this->moduleHandler->loadInclude('locale', 'fetch.inc');
      $this->moduleHandler->loadInclude('locale', 'bulk.inc');

      $langcodes = array_keys(locale_translatable_language_list());

      // Set the translation import options. This determines if existing
      // translations will be overwritten by imported strings.
      $config = $this->configFactory->get('locale.settings');
      $options = [
        'customized' => LOCALE_NOT_CUSTOMIZED,
        'overwrite_options' => [
          'not_customized' => $config->get('translation.overwrite_not_customized'),
          'customized' => $config->get('translation.overwrite_customized'),
        ],
        'finish_feedback' => TRUE,
        'use_remote' => locale_translation_use_remote_source(),
      ];
      if ($this->container->has(LocaleSource::class)) {
        $this->container->get(LocaleSource::class)->clearSources();
      }
      else {
        // Fallback for Drupal core before 11.4.0, which does not provide the
        // LocaleSource service yet.
        // @phpstan-ignore function.deprecated
        locale_translation_clear_status();
      }
      if ($this->container->has(LocaleFetch::class)) {
        $batch = $this->container->get(LocaleFetch::class)->buildUpdateBatch([], $langcodes, $options);
      }
      else {
        // Fallback for Drupal core before 11.4.0, which does not provide the
        // LocaleFetch service yet.
        // @phpstan-ignore function.deprecated
        $batch = locale_translation_batch_update_build([], $langcodes, $options);
      }
      batch_set($batch);
      // Set a batch to update configuration as well.
      if ($this->container->has(LocaleConfigBatch::class)) {
        $batch = $this->container->get(LocaleConfigBatch::class)->buildBatch($options, $langcodes);
      }
      else {
        // Fallback for Drupal core before 11.4.0, which does not provide the
        // LocaleConfigBatch service yet.
        // @phpstan-ignore function.deprecated
        $batch = locale_config_batch_update_components($options, $langcodes);
      }
      if ($batch) {
        batch_set($batch);
      }
      batch_process();

      // Allow other modules to jump in with translation update routines.
      $this->moduleHandler->invokeAll('drd_agent_update_translation');
    }
    return [];
  }

}
