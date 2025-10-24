<?php

namespace Drupal\personal_notes\Entity;

use Drupal\Core\Entity\Annotation\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\Entity\User;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the PersonalNote entity class.
 *
 * @ContentEntityType(
 *   id = "personal_note",
 *   label = @Translation("Personal Note"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\personal_notes\PersonalNotesListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\personal_notes\Form\PersonalNoteForm",
 *       "add" = "Drupal\personal_notes\Form\PersonalNoteForm",
 *       "edit" = "Drupal\personal_notes\Form\PersonalNoteForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "personal_note",
 *   field_ui_base_route ="entity.personal_notes.collection",
 *   admin_permission = "administer personal_notes",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *  links = {
 *     "canonical" = "/admin/structure/personal_note/{personal_note}",
 *     "add-form" = "/admin/structure/personal_note/add",
 *     "delete-form" = "/admin/structure/personal_note/{personal_note}/delete",
 *     "edit-form" = "/admin/structure/personal_note/{personal_note}/edit",
 *     "collection" = "/admin/structure/personal_notes",
 *   }
 * )
 */
class PersonalNote extends ContentEntityBase implements PersonalNoteInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * Determines the schema for the base_table property defined above.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t("Title"))
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['user'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t("User"))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 1,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);;


    $fields['note'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t("Note"))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 1,
        'settings' => [
          'rows' => 3,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);


    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time when the note was created.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time when the note was last edited.'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid']
      ->setLabel(t('Author'))
      ->setDescription(t('The note author.'))
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);


    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $value): PersonalNote {
    $this->set('title', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNote(): string {
    return $this->get('note')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setNote($value): PersonalNote {
    $this->set('note', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(): ?User {
    return $this->get('user')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setUser(string $user): PersonalNote {
    $this->set('user', $user);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): PersonalNote {
    $this->set('created', $timestamp);
    return $this;
  }

}

