<?php

declare(strict_types=1);

namespace Drupal\group\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\Storage\GroupRelationshipStorageInterface;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;

/**
 * Enhances entity and group forms to allow inline group relationship creation.
 *
 * @ingroup group
 */
class CreateFormEnhancer implements CreateFormEnhancerInterface {

  use StringTranslationTrait;

  /**
   * The name of the flag to inject into the original form.
   *
   * @var string
   */
  protected const string FLAG_NAME = '#group__create_form_is_enhanced';

  /**
   * The name of the fieldset to inject into the original form.
   *
   * @var string
   */
  protected const string FIELDSET_NAME = 'group_relationship';

  /**
   * The form state key to store the group relationship.
   *
   * @var string
   */
  protected const string GR_STORAGE_KEY = 'cfe_gr';

  /**
   * The form state key to store the group relationship values for creation.
   *
   * @var string
   */
  protected const string GRV_STORAGE_KEY = 'cfe_grv';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function isFormEnhanced(array $form): bool {
    return !empty($form[self::FLAG_NAME]);
  }

  /**
   * {@inheritdoc}
   */
  public function enhanceGroupFormSubmit(array &$submit): void {
    $submit['#submit'][] = [$this, 'submitGroupForm'];
  }

  /**
   * {@inheritdoc}
   */
  public function enhanceGroupForm(array &$form, FormStateInterface $form_state, array $relationship_values = []): void {
    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface);

    $this->enhanceForm(
      $form,
      $form_state,
      $form_object->getEntity(),
      'group_membership',
      'validateGroupForm',
      $this->t('Membership information'),
      $relationship_values,
    );
  }

  /**
   * Validates the group form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateGroupForm(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface);

    $this->validateForm(
      $form,
      $form_state,
      $form_object->getEntity(),
      (int) $this->currentUser->id(),
      'Failed to validate membership: @message',
    );
  }

  /**
   * Submits the group form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitGroupForm(array &$form, FormStateInterface $form_state): void {
    $this->submitForm($form, $form_state, 'gid');
  }

  /**
   * {@inheritdoc}
   */
  public function enhanceEntityFormSubmit(array &$submit): void {
    $submit['#submit'][] = [$this, 'submitEntityForm'];
  }

  /**
   * {@inheritdoc}
   */
  public function enhanceEntityForm(array &$form, FormStateInterface $form_state, GroupInterface $group, string $plugin_id, array $relationship_values = []): void {
    $this->enhanceForm(
      $form,
      $form_state,
      $group,
      $plugin_id,
      'validateEntityForm',
      $this->t('Relationship information'),
      $relationship_values,
    );
  }

  /**
   * Validates the entity form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateEntityForm(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface);

    $this->validateForm(
      $form,
      $form_state,
      $form_state->get('group'),
      $form_object->getEntity(),
      'Failed to validate relationship: @message',
    );
  }

  /**
   * Submits the entity form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitEntityForm(array &$form, FormStateInterface $form_state): void {
    $this->submitForm($form, $form_state, 'entity_id');
  }

  /**
   * Enhances the form.
   *
   * @param array $form
   *   The form to enhance.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group the relationship will belong to.
   * @param string $plugin_id
   *   The plugin the relationship will use.
   * @param string $validate_method
   *   Name of the method on this class used for validation.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $fieldset_title
   *   Title for the fieldset containing the relationship fields.
   * @param array $relationship_values
   *   (optional) Additional values to instantiate the relationship with.
   */
  protected function enhanceForm(array &$form, FormStateInterface $form_state, GroupInterface $group, string $plugin_id, string $validate_method, TranslatableMarkup $fieldset_title, array $relationship_values = []): void {
    // Flag the form as being enhanced so that alter hooks can easily figure out
    // if they are dealing with one, using ::isFormEnhanced().
    $form[self::FLAG_NAME] = TRUE;

    $form['#validate'][] = [$this, $validate_method];

    $gr_storage = $this->getGroupRelationshipStorage();
    $grt_storage = $this->getGroupRelationshipTypeStorage();

    $group_relationship_type_id = $grt_storage->getRelationshipTypeId($group->bundle(), $plugin_id);
    $group_relationship_values = ['type' => $group_relationship_type_id] + $relationship_values;
    $form_state->set(self::GRV_STORAGE_KEY, $group_relationship_values);

    // Provide a fieldset where we will put the group relationship form display.
    $form[self::FIELDSET_NAME] = [
      '#type' => 'fieldset',
      '#title' => $fieldset_title,
      '#weight' => 1986,
      // This is important so the individual fields map their value to entries
      // in an array. If we do not specify this and both the original form and
      // the relationship form have a field with the same name, we get into
      // trouble.
      '#parents' => [self::FIELDSET_NAME],
      '#tree' => TRUE,
    ];

    // Create a form display for the group relationship and attach its form to
    // the main form we're enhancing.
    $group_relationship = $gr_storage->create($group_relationship_values);
    $form_display = EntityFormDisplay::collectRenderDisplay($group_relationship, 'add');
    $form_display->buildForm($group_relationship, $form[self::FIELDSET_NAME], new FormState());
    $form_state->set('sub_form_display', $form_display);

    // Hide fields we will provide the value for.
    $form[self::FIELDSET_NAME]['entity_id']['#access'] = FALSE;
    $form[self::FIELDSET_NAME]['langcode']['#access'] = FALSE;

    // If nothing is left to show, hide the fieldset altogether.
    $form[self::FIELDSET_NAME]['#access'] = !empty(Element::getVisibleChildren($form[self::FIELDSET_NAME]));
  }

  /**
   * Validates the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\group\Entity\GroupInterface|int $group
   *   The group (ID) to set on the relationship.
   * @param \Drupal\Core\Entity\EntityInterface|int $entity
   *   The target entity (ID) to set on the relationship.
   * @param string $error_message
   *   The error message to display when something didn't validate. Use the
   *   "@message" placeholder for the violation message.
   */
  protected function validateForm(array &$form, FormStateInterface $form_state, GroupInterface|int $group, EntityInterface|int $entity, string $error_message): void {
    $group_relationship = $this->getGroupRelationshipStorage()->create($form_state->get(self::GRV_STORAGE_KEY));

    if ($form[self::FIELDSET_NAME]['#access'] !== FALSE) {
      $form_display = $form_state->get('sub_form_display');
      assert($form_display instanceof EntityFormDisplay);
      $form_display->extractFormValues($group_relationship, $form[self::FIELDSET_NAME], $form_state);
    }

    // We only set these fields after the display had its say, or they would get
    // overwritten immediately by it.
    $group_relationship->set('entity_id', $entity);
    $group_relationship->set('gid', $group);
    $form_state->set(self::GR_STORAGE_KEY, $group_relationship);

    $violations = $group_relationship->validate();
    foreach ($violations->getEntityViolations() as $violation) {
      $form_state->setError($form, $this->t($error_message, ['@message' => $violation->getMessage()]));
    }
  }

  /**
   * Submits the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $base_field
   *   The base field on the relationship to store the newly created entity.
   */
  protected function submitForm(array &$form, FormStateInterface $form_state, string $base_field): void {
    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface);

    $entity = $form_object->getEntity();
    $group_relationship = $form_state->get(self::GR_STORAGE_KEY);
    $group_relationship->set($base_field, $entity->id());
    $this->getGroupRelationshipStorage()->save($group_relationship);
  }

  /**
   * Gets the group relationship storage.
   *
   * @return \Drupal\group\Entity\Storage\GroupRelationshipStorageInterface
   *   The group relationship storage.
   */
  protected function getGroupRelationshipStorage(): GroupRelationshipStorageInterface {
    return $this->entityTypeManager->getStorage('group_relationship');
  }

  /**
   * Gets the group relationship type storage.
   *
   * @return \Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface
   *   The group relationship type storage.
   */
  protected function getGroupRelationshipTypeStorage(): GroupRelationshipTypeStorageInterface {
    return $this->entityTypeManager->getStorage('group_relationship_type');
  }

}
