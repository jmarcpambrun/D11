<?php

namespace Drupal\protect_form_flood_control;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface defining functions for Protect Form Flood Control Manager classes.
 */
interface ManagerInterface {

  /**
   * The max length for the form_id we can save in the database (event column).
   */
  const MAX_LENGTH = 64;

  /**
   * Alter form belong module settings.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state object.
   * @param string $form_id
   *   The form ID.
   */
  public function alterForm(array &$form, FormStateInterface $form_state, string $form_id);

  /**
   * Should we protect all forms?
   *
   * @return bool
   *   Return TRUE if we should protect all forms.
   */
  public function protectAllForms();

  /**
   * Should we log the blocked submissions?
   *
   * @return bool
   *   Return TRUE if we should log blocked submissions.
   */
  public function shouldLogBlockedSubmissions();

  /**
   * Log the blocked submission.
   *
   * @param string $form_id
   *   The from ID.
   * @param int $window
   *   The flood control window.
   * @param int $threshold
   *   The flood control threshold.
   */
  public function logBlockedSubmission(string $form_id, int $window, int $threshold);

  /**
   * Check if the form is a system form.
   *
   * @param string $form_id
   *   The form id.
   *
   * @return bool
   *   Return TRUE if the form is a system form.
   */
  public function formIsSystemForm(string $form_id);

  /**
   * Gets the general window.
   *
   * @return int
   *   The general flood control window.
   */
  public function getWindow();

  /**
   * Gets the general threshold.
   *
   * @return int
   *   The general flood control threshold.
   */
  public function getThreshold();

  /**
   * Shows the form IDs.
   *
   * @return bool
   *   Return TRUE if we should display form IDs for debugging purpose.
   */
  public function showIds();

  /**
   * Gets the general form IDs protected.
   *
   * @return array
   *   The flood control form IDs.
   */
  public function getProtectedFormIds();

  /**
   * Gets the general form IDs unprotected.
   *
   * @return array
   *   The flood control form IDs.
   */
  public function getUnprotectedFormIds();

  /**
   * Gets the general IP addresses allowlisted.
   *
   * @return array
   *   The IP addresses allowlisted.
   */
  public function getAllowlist();

  /**
   * Gets the protected forms IDs as patterns for the path matcher.
   *
   * @return string
   *   The form IDs as a string.
   */
  public function getProtectedFormIdsPatterns();

  /**
   * Gets the unprotected forms IDs as patterns for the path matcher.
   *
   * @return string
   *   The form IDs as a string.
   */
  public function getUnprotectedFormIdsPatterns();

  /**
   * Gets the IP addresses for the path matcher.
   *
   * @return string
   *   The IP addresses as a string.
   */
  public function getAllowlistPatterns();

  /**
   * Should we display form ID?
   *
   * @return bool
   *   Return TRUE if current user has permissions.
   */
  public function shouldDisplayFormId();

  /**
   * Gets the base form ID if available.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return string
   *   The base form ID.
   */
  public function getBaseFormId(FormStateInterface $form_state);

  /**
   * Display the form ID and base form ID.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $form_id
   *   The form id.
   */
  public function displayFormId(FormStateInterface $form_state, string $form_id);

  /**
   * Should we bypass flood control?
   *
   * @param array $form
   *   The form.
   *
   * @return bool
   *   Return TRUE if current user has permissions to bypass flood control.
   */
  public function byPassFloodControl(array &$form);

  /**
   * Is the client IP address in the allowlist?
   *
   * @param array $form
   *   The form.
   *
   * @return bool
   *   Return TRUE if client IP is in the allowlist.
   */
  public function clientIpIsInAllowlist(array &$form);

  /**
   * Is the form protected?
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $form_id
   *   The form id.
   *
   * @return bool
   *   TRUE if the form is protected.
   */
  public function formIsProtected(FormStateInterface $form_state, string $form_id);

  /**
   * Check if the form or base form ID match patterns.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $form_id
   *   The form id.
   * @param string $patterns
   *   The patterns to check.
   *
   * @return bool
   *   Return TRUE if the form ID or base form ID match the patterns.
   */
  public function formMatchPatterns(FormStateInterface $form_state, string $form_id, string $patterns);

  /**
   * Gets the individual form configurations.
   *
   * @return array
   *   An array of form configuration with ids, window and threshold as keys.
   */
  public function getIndividualFormConfigurations();

  /**
   * Massage an array of IDs as patterns.
   *
   * @param array $ids
   *   The IDs.
   *
   * @return string
   *   The IDs as patterns.
   */
  public function massageIdsAsPattern(array $ids);

  /**
   * Gets the flood control configuration for a form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form_state object.
   * @param string $form_id
   *   The form ID.
   *
   * @return array
   *   The flood control configuration with window and threshold as keys.
   */
  public function getFormConfiguration(FormStateInterface $form_state, string $form_id);

  /**
   * Gets the flood service.
   *
   * @return \Drupal\Core\Flood\FloodInterface
   *   The flood service.
   */
  public function getFlood();

  /**
   * Gets the date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter service.
   */
  public function getDateFormatter();

  /**
   * Truncate the form_id to a string less than 64 characters.
   *
   * @param string $form_id
   *   The form ID to truncate.
   *
   * @return string
   *   The form ID truncated.
   */
  public function truncateFormId(string $form_id);

}
