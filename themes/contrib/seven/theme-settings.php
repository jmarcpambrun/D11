<?php

/**
 * @file
 * Functions to support Seven theme settings.
 */

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\LegacyHook;

/**
 * Implements hook_form_FORM_ID_alter() for system_theme_settings.
 */
#[LegacyHook]
function seven_form_system_theme_settings_alter(&$form, FormStateInterface $form_state): void {
  $enable_contextual_links = Drupal::service(ThemeSettingsProvider::class)->getSetting('enable_block_contextual_links');

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
