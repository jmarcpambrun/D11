<?php

namespace Drupal\field_group;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface definition for fieldgroup formatter plugins.
 *
 * @ingroup field_group_formatter
 */
interface FieldGroupFormatterInterface extends PluginInspectionInterface {

  /**
   * Field formatter process function.
   *
   * Allows the field group formatter to manipulate the field group array and
   * attach the formatters elements. The process method is called in the
   * #process part of theme layer, and is currently used for forms. The
   * preRender method is called in the #pre_render part of the theme layer,
   * and is currently used for entity displays.
   *
   * @param array $element
   *   The field group render array.
   * @param array $processed_array
   *   The render array of the form this group is being built within.
   */
  public function process(array &$element, $processed_array);

  /**
   * Field formatter prerender function.
   *
   * Allows the field group formatter to manipulate the field group array and
   * attach the formatters rendering element.
   *
   * @param array $element
   *   The field group render array.
   * @param array $render_array
   *   The render array of the entity or form this group is being built within.
   */
  public function preRender(array &$element, $render_array);

  /**
   * Returns a form to configure settings for the formatter.
   *
   * Invoked in field_group_field_ui_display_form_alter to allow
   * administrators to configure the formatter. The field_group module takes
   * care of handling submitted form values.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form elements for the formatter settings.
   */
  public function settingsForm(array $form, FormStateInterface $form_state);

  /**
   * Returns a short summary for the current formatter settings.
   *
   * If an empty result is returned, a UI can still be provided to display
   * a settings form in case the formatter has configurable settings.
   *
   * @return array
   *   A short summary of the formatter settings.
   */
  public function settingsSummary();

  /**
   * Defines the default settings for this plugin.
   *
   * @param string $context
   *   The context to get the default settings for.
   *
   * @return array
   *   A list of default settings, keyed by the setting name.
   */
  public static function defaultContextSettings($context);

}
