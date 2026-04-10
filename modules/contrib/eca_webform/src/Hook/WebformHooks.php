<?php

namespace Drupal\eca_webform\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Event\TriggerEvent;
use Drupal\webform\Plugin\WebformHandlerInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Implements webform hooks for the eca_webform module.
 */
class WebformHooks {

  /**
   * Constructs a new BaseHooks object.
   */
  public function __construct(
    protected TriggerEvent $triggerEvent,
    protected AccountInterface $currentUser,
  ) {
  }

  /**
   * Implements hook_webform_element_info_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_element_info_alter')]
  public function elementInfoAlter(array &$definitions): void {
    $this->triggerEvent->dispatchFromPlugin('webform:element_info_alter', $definitions);
  }

  /**
   * Implements hook_webform_handler_info_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_handler_info_alter')]
  public function handlerInfoAlter(array &$handlers): void {
    $this->triggerEvent->dispatchFromPlugin('webform:handler_info_alter', $handlers);
  }

  /**
   * Implements hook_webform_variant_info_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_variant_info_alter')]
  public function variantInfoAlter(array &$variants): void {
    $this->triggerEvent->dispatchFromPlugin('webform:variant_info_alter', $variants);
  }

  /**
   * Implements hook_webform_source_entity_info_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_source_entity_info_alter')]
  public function sourceEntityInfoAlter(array &$definitions): void {
    $this->triggerEvent->dispatchFromPlugin('webform:source_entity_info_alter', $definitions);
  }

  /**
   * Implements hook_webform_element_default_properties_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_element_default_properties_alter')]
  public function elementDefaultPropertiesAlter(array &$properties, array &$definition): void {
    $this->triggerEvent->dispatchFromPlugin('webform:element_default_properties_alter', $properties, $definition);
  }

  /**
   * Implements hook_webform_element_translatable_properties_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_element_translatable_properties_alter')]
  public function elementTranslatablePropertiesAlter(array &$properties, array &$definition): void {
    $this->triggerEvent->dispatchFromPlugin('webform:element_translatable_properties_alter', $properties, $definition);
  }

  /**
   * Implements hook_webform_element_configuration_form_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_element_configuration_form_alter')]
  public function elementConfigurationFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->triggerEvent->dispatchFromPlugin('webform:element_configuration_form_alter', $form, $form_state);
  }

  /**
   * Implements hook_webform_element_alter().
   *
   * @see hook_webform_element_ELEMENT_TYPE_alter
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_element_alter')]
  public function elementAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $this->triggerEvent->dispatchFromPlugin('webform:element_alter', $element, $form_state, $context);
  }

  /**
   * Implements hook_webform_element_access().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_element_access')]
  public function elementAccess(string $operation, array &$element, ?AccountInterface $account = NULL, array $context = []): AccessResultInterface {
    $result = NULL;
    if ($account === NULL) {
      $account = $this->currentUser;
    }
    /** @var \Drupal\eca_webform\Event\ElementAccessAlter|null $event */
    $event = $this->triggerEvent->dispatchFromPlugin('webform:element_access', $operation, $element, $account, $context);
    $result = $event?->getAccessResult();
    return $result ?? AccessResult::neutral();
  }

  /**
   * Implements hook_webform_element_input_masks().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_element_input_masks')]
  public function elementInputMasks(): array {
    /**
     * @var \Drupal\eca_webform\Event\ElementInputMasks|null $event
     */
    $event = $this->triggerEvent->dispatchFromPlugin('webform:element_input_masks');
    if ($event !== NULL) {
      return $event->getInputMasks();
    }
    return [];
  }

  /**
   * Implements hook_webform_element_input_masks_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_element_input_masks_alter')]
  public function elementInputMasksAlter(array &$input_masks): void {
    $this->triggerEvent->dispatchFromPlugin('webform:element_input_masks_alter', $input_masks);
  }

  /**
   * Implements hook_webform_options_alter().
   *
   * @see hook_webform_options_WEBFORM_OPTIONS_ID_alter()
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_options_alter')]
  public function optionsAlter(array &$options, array &$element, ?string $options_id = NULL): void {
    $this->triggerEvent->dispatchFromPlugin('webform:options_alter', $options, $element, $options_id);
  }

  /**
   * Implements hook_webform_submissions_pre_purge().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_submissions_pre_purge',)]
  public function submissionsPrePurge(array $webform_submissions): void {
    $this->triggerEvent->dispatchFromPlugin('webform:submissions_pre_purge', $webform_submissions);
  }

  /**
   * Implements hook_webform_submissions_post_purge().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_submissions_post_purge',)]
  public function submissionsPostPurge(array $webform_submissions): void {
    $this->triggerEvent->dispatchFromPlugin('webform:submissions_post_purge', $webform_submissions);
  }

  /**
   * Implements hook_webform_submission_form_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_submission_form_alter')]
  public function submissionFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $this->triggerEvent->dispatchFromPlugin('webform:submission_form_alter', $form, $form_state, $form_id);
  }

  /**
   * Implements hook_webform_admin_third_party_settings_form_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_admin_third_party_settings_form_alter')]
  public function adminThirdPartySettingsFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->triggerEvent->dispatchFromPlugin('webform:admin_third_party_settings_form_alter', $form, $form_state);
  }

  /**
   * Implements hook_webform_third_party_settings_form_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_third_party_settings_form_alter')]
  public function thirdPartySettingsFormAlter(array &$form, FormStateInterface $form_state): void {
    $this->triggerEvent->dispatchFromPlugin('webform:third_party_settings_form_alter', $form, $form_state);
  }

  /**
   * Implements hook_webform_handler_invoke_alter().
   *
   * @see hook_webform_handler_invoke_METHOD_NAME_alter()
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_handler_invoke_alter')]
  public function handlerInvokeAlter(WebformHandlerInterface $handler, string $method_name, array &$args): void {
    $this->triggerEvent->dispatchFromPlugin('webform:handler_invoke_alter', $handler, $method_name, $args);
  }

  /**
   * Implements hook_webform_help_info().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_help_info')]
  public function helpInfo(): array {
    /**
     * @var \Drupal\eca_webform\Event\HelpInfo|null $event
     */
    $event = $this->triggerEvent->dispatchFromPlugin('webform:help_info');
    if ($event !== NULL) {
      return $event->getHelpInfo();
    }
    return [];
  }

  /**
   * Implements hook_webform_help_info_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_help_info_alter')]
  public function helpInfoAlter(array &$help): void {
    $this->triggerEvent->dispatchFromPlugin('webform:help_info_alter', $help);
  }

  /**
   * Implements hook_webform_access_rules().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_access_rules')]
  public function accessRules(): array {
    /**
     * @var \Drupal\eca_webform\Event\AccessRules|null $event
     */
    $event = $this->triggerEvent->dispatchFromPlugin('webform:access_rules');
    if ($event !== NULL) {
      return $event->getAccessRules();
    }
    return [];
  }

  /**
   * Implements hook_webform_access_rules_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_access_rules_alter')]
  public function accessRulesAlter(array &$access_rules): void {
    $this->triggerEvent->dispatchFromPlugin('webform:access_rules_alter', $access_rules);
  }

  /**
   * Implements hook_webform_submission_access().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_submission_access')]
  public function submissionAccess(WebformSubmissionInterface $webform_submission, string $operation, AccountInterface $account): AccessResultInterface {
    $result = NULL;
    /** @var \Drupal\eca_webform\Event\SubmissionAccess|null $event */
    $event = $this->triggerEvent->dispatchFromPlugin('webform:submission_access', $webform_submission, $operation, $account);
    $result = $event?->getAccessResult();
    return $result ?? AccessResult::neutral();
  }

  /**
   * Implements hook_webform_submission_query_access_alter().
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_submission_query_access_alter')]
  public function submissionQueryAccessAlter(AlterableInterface $query, array $webform_submission_tables): void {
    $this->triggerEvent->dispatchFromPlugin('webform:submission_query_access_alter', $query, $webform_submission_tables);
  }

  /**
   * Implements hook_webform_image_select_images_alter().
   *
   * @see hook_webform_image_select_images_WEBFORM_IMAGE_SELECT_IMAGES_ID_alter()
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('webform_image_select_images_alter')]
  public function imageSelectImagesAlter(array &$images, array &$element, ?string $images_id = NULL): void {
    $this->triggerEvent->dispatchFromPlugin('webform:image_select_images_alter', $images, $element, $images_id);
  }

}
