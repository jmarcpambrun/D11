<?php

namespace Drupal\drd;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * An update service used for updating encryption on domains and host entities.
 */
class EncryptionUpdate {

  use MessengerTrait;

  /**
   * The encryption service.
   *
   * @var \Drupal\drd\Encryption
   */
  protected Encryption $encryptionService;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * EncryptionUpdate constructor.
   *
   * @param \Drupal\drd\Encryption $encryptionService
   *   DRD encryption service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(Encryption $encryptionService, ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->encryptionService = $encryptionService;
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Update method used to update sensitive values in entities and config.
   *
   * By the time of calling this function, the new encryption profile needs
   * to be configured and assigned to be used by DRD.
   *
   * @param string $old_profile_id
   *   The ID of the previously used profile.
   * @param string $new_profile_id
   *   The ID of the new profile.
   */
  public function update(string $old_profile_id, string $new_profile_id): void {
    $this->encryptionService->setOldProfileId($old_profile_id, $new_profile_id);

    // Update entity values.
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($definition->entityClassImplements(EncryptionEntityInterface::class)) {
        try {
          $storage = $this->entityTypeManager->getStorage($definition->id());
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException) {
          // @todo Log this exception.
          continue;
        }
        /** @var \Drupal\drd\EncryptionEntityInterface $entity */
        foreach ($storage->loadMultiple() as $entity) {
          foreach ($entity->getEncryptedFieldNames() as $encryptedFieldName) {
            if ($entity instanceof ContentEntityInterface) {
              $values = $entity->get($encryptedFieldName)->getValue();
              $value = empty($values) ? [] : $values[0];
            }
            elseif ($entity instanceof ConfigEntityInterface) {
              $value = $entity->get($encryptedFieldName);
            }
            else {
              continue;
            }
            $this->encryptionService->encrypt($value);
            $entity->set($encryptedFieldName, $value);
          }
          try {
            $entity->save();
          }
          catch (EntityStorageException) {
            // @todo Log this exception.
          }
        }
      }
    }

    // Update config values.
    $config = $this->configFactory->getEditable('drd.general');
    $key = 'local.db.pass';
    $value = $config->get($key);
    $this->encryptionService->encrypt($value);
    $config->set($key, $value);
    $config->save();

    $this->messenger()->addMessage('All sensitive data has been (re-)encrypted.');
  }

}
