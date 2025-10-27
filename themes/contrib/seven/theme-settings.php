<?php

/**
 * @file
 * Functions to support Seven theme settings.
 */

// phpcs:disable Drupal.Commenting.DocComment.ContentAfterOpen
/* @noinspection PhpUnusedParameterInspection, PhpUnused */
// phpcs:enable

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for system_theme_settings.
 */
function seven_form_system_theme_settings_alter(&$form, FormStateInterface $form_state): void {
  $enable_contextual_links = DeprecationHelper::backwardsCompatibleCall(
    currentVersion: Drupal::VERSION,
    deprecatedVersion: '11.3',
    // @phpstan-ignore-next-line
    currentCallable: fn() => Drupal::service(ThemeSettingsProvider::class)->getSetting('enable_block_contextual_links'),
    deprecatedCallable: fn() => theme_get_setting('enable_block_contextual_links'),
  );

  $form['seven_settings'] = [
    '#type' => 'fieldset',
    '#title' => t('Seven utilities'),
  ];

  $form['seven_settings']['enable_block_contextual_links'] = [
    '#type' => 'checkbox',
    '#title' => t('Enable contextual links for blocks'),
    '#default_value' => $enable_contextual_links,
  ];
}
