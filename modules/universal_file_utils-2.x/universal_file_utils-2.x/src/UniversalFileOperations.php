<?php

namespace Drupal\universal_file_utils;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\universal_file_utils\Event\UniversalFileAccessEvent;
use Drupal\universal_file_utils\Event\UniversalFileDownloadEvent;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UniversalFileOperations.
 */
class UniversalFileOperations implements UniversalFileOperationsInterface {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * @var EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * @var FileUsageInterface
   */
  protected $fileUsage;

  /**
   * @var Time
   */
  protected $time;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new UniversalFileOperations object.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *
   * @param FileUsageInterface $fileUsage
   *
   * @param Time $time
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *  The file system.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              FileUsageInterface $fileUsage, Time $time,
                              FileSystemInterface $file_system) {
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->fileUsage = $fileUsage;
    $this->time = $time;
    $this->fileSystem = $file_system;
  }

  /**
   * Use an event to process hook_file_download() and then return whatever the
   * event subscribers have to say about it and react.
   *
   * @param $uri
   *
   * @return string[] | -1 | null
   */
  public function HookFileDownload($uri) {
    // Let's fetch the file data properly (see file_file_download()).

    /* @var FileInterface[] $files */
    $files = $this->fileStorage->loadByProperties(['uri' => $uri]);

    /** @var FileInterface $file */
    $file = NULL;

    if (count($files)) {
      foreach ($files as $item) {
        // Database servers may use case-insensitive comparison,
        // check more to find a filename that is an exact match.
        if ($item->getFileUri() === $uri) {
          $file = $item;
          break;
        }
      }
    }

    if (empty($file)) {
      \Drupal::logger('file-download')->error('Could not download file "%u" because there was no exact name match.', ['%u' => $uri]);
      // Failed to match a file at all.
      return NULL;
    }

    // Send the gathered information to anyone who wants to listen,
    // and returns the (possibly) modified version of the headers.
    /** @var UniversalFileDownloadEvent $event */
    $event = UniversalFileDownloadEvent::Dispatch($file, $this->fileUsage);

    // Return the headers value (array or -1 for download denial).
    return $event->getHeaders();
  }

  /**
   * Use an event to process hook_file_access() and then return whatever the
   * event subscribers have to say about it.
   *
   * @param FileInterface $file
   * @param string $operation
   * @param AccountInterface $account
   *
   * @return AccessResultInterface
   */
  public function HookFileAccess($file, $operation, AccountInterface $account) {
    /** @var UniversalFileAccessEvent $event */
    $event = UniversalFileAccessEvent::Dispatch($file, $this->fileUsage, [
      'operation' => $operation,
      'account' => $account,
    ]);

    return $event->getAccessResult();
  }

  /**
   * Make a copy of the file into the required folder, and mark as permanent.
   *
   * @param int $fid
   * @param string $folderName
   * @param string|null $subFolderName
   *
   * @return FileInterface|null
   */
  public function fileCopy(int $fid, string $folderName, ?string $subFolderName = NULL): ?FileInterface {
    $newFile = NULL;

    /** @var FileInterface $file */
    if ($file = $this->fileStorage->load($fid)) {

      if (empty($subFolderName)) {
        $time = $this->time->getRequestTime();
        $subFolderName = date('Y-m', $time);
      }

      $directory = sprintf('private://%s/%s', $folderName, $subFolderName);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $destination = sprintf('%s/%s', $directory, $file->getFilename());

      /* JMP_DBG if ($newFile = file_copy($file, $destination)) { JMP_DBG*/
	  if ($newFile = \Drupal::service('file.repository')->copy($file, $destination)) {
        try {
          $newFile->setPermanent();
          $newFile->save();
        }
        catch (EntityStorageException $e) {
          $this->getLogger('universal_file_utils_ops')
            ->error('Could not save file object because: %x', ['%e' => $e->getMessage()]);
          $newFile = NULL;
        }
      }
      else {
        $this->getLogger('universal_file_utils_ops')
          ->error('Unable to make a copy of the source file object.');
      }
    }
    else {
      $this->getLogger('universal_file_utils_ops')
        ->error('Could not find source file object.');
    }

    return $newFile;
  }

  /**
   * If this is a valid file, delete it.
   *
   * @param int $fid
   *
   * @return bool
   */
  public function fileRemove(int $fid): bool {
    $success = FALSE;
    if ($fid) {
      // Load the object of the file by its fid
      if ($file = File::load($fid)) {
        // Delete also removes the entry from file usage.
        try {
          $file->delete();
          $success = TRUE;
        }
        catch (EntityStorageException $e) {
          $this->getLogger('universal_file_utils_ops')
            ->error('Could not delete file because: %x', ['%x' => $e->getMessage()]);
        }
      }
      else {
        // No such file, we'll pretend it's a success.
        $success = TRUE;
      }
    }
    return $success;
  }
}
