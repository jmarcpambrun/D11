<?php

namespace Drupal\universal_file_utils;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;

/**
 * Interface UniversalFileOperationsInterface.
 */
interface UniversalFileOperationsInterface {

  /**
   * Use an event to process hook_file_download() and then return whatever the
   * event subscribers have to say about it.
   *
   * @param $uri
   *
   * @return string[] | -1 | null
   */
  public function HookFileDownload($uri);

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
  public function HookFileAccess($file, $operation, AccountInterface $account);

  /**
   * Make a copy of the file into the required folder, and mark as permanent.
   *
   * @param int $fid
   * @param string $folderName
   *
   * @param string|null $subFolderName
   *
   * @return FileInterface | null
   */
  public function fileCopy(int $fid, string $folderName, ?string $subFolderName = NULL): ?FileInterface;

  /**
   * If this is a valid file, delete it.
   *
   * @param int $fid
   */
  public function fileRemove(int $fid);


}
