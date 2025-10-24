<?php

namespace Drupal\webform_entity_builder\Plugin;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\file\FileStorageInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\universal_file_utils\UniversalFileOperations;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Entity builder plugins.
 */
abstract class EntityBuilderBase extends PluginBase implements EntityBuilderInterface, ContainerFactoryPluginInterface {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * @var FileUsageInterface
   */
  protected $fileUsage;

  /**
   * @var AccountInterface
   */
  protected $account;

  /**
   * @var Time
   */
  protected $time;

  /**
   * @var FileStorageInterface
   */
  protected $fileStorage;

  /**
   * @var EntityInterface
   */
  protected $sourceEntity;

  /**
   * @var UniversalFileOperations
   */
  protected $fileOperations;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EntityBuilderBase constructor.
   *
   * @param FileUsageInterface $fileUsage
   * @param AccountInterface $account
   * @param Time $time
   * @param EntityTypeManagerInterface $entityTypeManager
   * @param UniversalFileOperations $fileOperations
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param array $plugin_definition
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    FileUsageInterface      $fileUsage,
    AccountInterface $account,
    Time                    $time,
    EntityTypeManagerInterface $entityTypeManager,
    UniversalFileOperations $fileOperations,
    array                   $configuration, $plugin_id, $plugin_definition
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUsage = $fileUsage;
    $this->account = $account;
    $this->time = $time;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->fileOperations = $fileOperations;
  }

  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return EntityBuilderBase
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('file.usage'),
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('universal_file_utils.operations'),
      $configuration, $plugin_id, $plugin_definition
    );
  }

  /**
   * Compile the values to create/edit an entity (usually from the webform).
   *
   * @param mixed[] $data
   *
   * @return mixed[]
   */
  public function build(array $data) {
    // Save the source entity if there is one.
    $this->sourceEntity = $data['source_entity'] ?? NULL;

    // Perform any processing on the raw data if we need to.
    $this->preProcessData($data);

    // Build the values for creating this new entity.
    $values = $this->entitySpecificFields($data);

    // Aaaand any processing on the finished values...?
    $this->postProcessValues($values);

    // Give them back ready to create the actual entity.
    return $values;
  }

  /**
   * An opportunity to modify the raw data before creating the values array.
   *
   * @param mixed[] $data
   */
  protected function preProcessData(array &$data) {}

  /**
   * Build the entity-specific values.
   *
   * @param mixed[] $data
   *
   * @return mixed[]
   */
  abstract protected function entitySpecificFields(array $data);

  /**
   * An opportunity to modify the final values before returning them.
   *
   * @param mixed[] $values
   */
  protected function postProcessValues(array &$values) {}

  /**
   * Perform any finalisation needed (such as attaching files to the entity)
   *
   * @param EntityInterface $entity
   */
  public function finalise(EntityInterface $entity) {
    // Perform entity-type-specific finalisation
    $this->doFinalise($entity);

    // Finally, if there is a source entity specified, we delete it.
    if (!empty($this->sourceEntity)) {
      try {
        $this->sourceEntity->delete();
      }
      catch (EntityStorageException $e) {
        $this->getLogger('entity_builder_base')
          ->error('Unable to delete the source entity because: ' . $e->getMessage());
      }
    }
  }

  /**
   * Perform entity-type specific finalisation.
   *
   * @param EntityInterface $entity
   */
  protected function doFinalise(EntityInterface $entity) {
    // Override as needed.
  }

  /**
   * Return the timezone, or the user's one.
   *
   * @param string $tz
   *
   * @return string
   */
  protected function prepareTimeZone($tz) {
    if ($tz == '_author') {
      $tz = $this->account->getTimeZone();
    }
    return $tz;
  }

  /**
   * Make a copy of the file into the required folder.
   *
   * @param int $fid
   * @param $folderName
   *
   * @return FileInterface | null
   */
  protected function fileCopy($fid, $folderName) {
    if (empty($fid)) {
      return NULL;
    }
    $dirname = $this->getDirectoryName();
    $folderName = "{$dirname}/{$folderName}";

    return $this->fileOperations->fileCopy($fid, $folderName);
  }

  /**
   * The directory name for saving the files, override as needed.
   *
   * @return string
   */
  protected function getDirectoryName() {
    return 'entity_files';
  }

}
