<?php
/**
 * @file
 * Contains \Drupal\personal_notes\Entity\PersonalNoteInterface.
 */

namespace Drupal\personal_notes\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\Entity\User;
use Drupal\user\EntityOwnerInterface;

/**
 * Represents a Personal Note Entity.
 */
interface PersonalNoteInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the title value.
   *
   * @return string
   *    The title.
   */
  public function getTitle(): string;

  /**
   * Sets the title.
   *
   * @param string $value
   *    The value of the title.
   *
   * @return \Drupal\personal_notes\Entity\PersonalNote
   *   The Personal Note entity.
   */
  public function setTitle(string $value): PersonalNote;

  /**
   * The note.
   *
   * @return string
   *    The note.
   */
  public function getNote(): string;

  /**
   * Set the note data.
   *
   * @param string $value
   *   The note data.
   *
   * @return \Drupal\personal_notes\Entity\PersonalNote
   *    The Personal Note entity.
   */
  public function setNote(string $value): PersonalNote;


  /**
   * Gets the user this note is attached to.
   *
   * @return User|null
   *    The user id.
   */
  public function getUser(): ?User;

  /**
   * Set the note data.
   *
   * @param string $user
   *   The user uid.
   *
   * @return \Drupal\personal_notes\Entity\PersonalNote
   *    The Personal Note entity.
   */
  public function setUser(string $user): PersonalNote;

  /**
   * Gets the created timestamp.
   *
   * @return int
   *    The unix timestamp.
   */
  //public function getCreatedTime(): int;

  /**
   * Sets the createdTime timestamp.
   *
   * @param int $value
   *    The unix timestamp.
   *
   * @return \Drupal\personal_notes\Entity\PersonalNote
   *    The Personal Note entity.
   */
  //public function setCreatedTime(int $value): PersonalNote;

}
