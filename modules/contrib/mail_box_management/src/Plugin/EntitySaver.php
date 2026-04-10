<?php

namespace Drupal\mail_box_management\Plugin;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

/**
 * EntitySaver handles the saving of mail nodes.
 *
 * @file
 * EntitySaver.php contain EntitySaver class.
 */

/**
 * EntitySaver handles the saving of mail nodes.
 *
 * @class
 * EntitySaver handles the saving of mail nodes.
 */
class EntitySaver {

  /**
   * Entities data to be saved.
   *
   * @var array
   */
  private array $entities = [];

  /**
   * Add entity data to be saved.
   *
   * @param array $entities
   *   Data for new entities.
   * @param int $owner_uid
   *   Entity owner id.
   *
   * @return void
   *   nothing is returned.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function add(array $entities, int $owner_uid = 1): void {

    if (array_key_exists(0, $entities)) {
      foreach ($entities as $key => $entity) {
        if (!empty($entity['header']['Subject'])) {

          $attachments = [];
          if (!empty($entity['attachments'])) {
            foreach ($entity['attachments'] as $attachment) {
              $file_type = strtolower($attachment['type']);
              $stored_path = $this->write($file_type, base64_decode($attachment['content']));
              $file = File::create([
                'uri' => $stored_path,
              ]);
              $file->setOwnerId($owner_uid);
              $file->setPermanent();
              $file->save();
              $attachment[] = ['target_id' => $file->id()];
            }
          }

          $this->entities[] = [
            'title' => $entity['header']['Subject'],
            'from_email' => $entity['header']['from_email'] ?? '',
            'from_name' => $entity['header']['from_name'] ?? '',
            'to_email' => $entity['header']['to_email'] ?? '',
            'to_name' => $entity['header']['to_name'] ?? '',
            'content' => $entity['body']['content'] ?? '',
            'attachment' => $attachments,
            'type' => 'mail_box_content',
          ];
        }
      }
    }
    else {
      if (!empty($entities['header']['Subject'])) {
        $entity = $entities;
        $attachments = [];
        if (!empty($entity['attachments'])) {
          foreach ($entity['attachments'] as $key => $attachment) {
            $file_type = strtolower($attachment['type']);
            $stored_path = $this->write($file_type, base64_decode($attachment['content']));
            $file = File::create([
              'uri' => $stored_path,
            ]);
            $file->setOwnerId($owner_uid);
            $file->setPermanent();
            $file->save();
            $attachments[] = ['target_id' => $file->id()];
          }
        }

        $this->entities[] = [
          'title' => $entity['header']['Subject'],
          'from_email' => $entity['header']['from_email'] ?? '',
          'from_name' => $entity['header']['from_name'] ?? '',
          'to_email' => $entity['header']['to_email'] ?? '',
          'to_name' => $entity['header']['to_name'] ?? '',
          'content' => $entity['body']['content'] ?? '',
          'attachment' => $attachments,
          'type' => 'mail_box_content',
        ];
      }
    }
  }

  /**
   * Save the entities data added.
   *
   * @return bool
   *   True if node were created.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(): bool {

    $flags = [];
    foreach ($this->entities as $entity) {
      $flags[] = !empty(Node::create($entity)->save());
    }
    return in_array(TRUE, $flags);
  }

  /**
   * Files writer.
   *
   * @param string $file_type
   *   File type to be created.
   * @param string $data
   *   Data for the file.
   *
   * @return string|null
   *   Path to the file is return or null if failed.
   */
  private function write(string $file_type, string $data): ?string {
    $directory = "public://mailbox_management/attachments";
    $file_system = mail_box_management_service('file_system');
    try {
      if ($file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        $storage_path = $directory . '/attachment-' . time() . '.' . $file_type;
        $file_system->saveData($data, $storage_path);
        return $storage_path;
      }
    }
    catch (\Exception) {
    }
    return NULL;
  }

  /**
   * Static instance this class.
   *
   * @param array $data
   *   Entity data to create node.
   * @param int $owner_id
   *   Owner id.
   *
   * @return \Drupal\mail_box_management\Plugin\EntitySaver
   *   This object is return.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function create(array $data, int $owner_id): EntitySaver {
    $new = new static();
    $new->add($data, $owner_id);
    return $new;
  }

}
