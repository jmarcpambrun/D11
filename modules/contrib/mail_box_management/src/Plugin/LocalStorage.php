<?php

namespace Drupal\mail_box_management\Plugin;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\Yaml\Yaml;

/**
 * LocalStorage.php contains class LocalStorage.
 *
 * @file
 * LocalStorage.
 */

/**
 * LocalStorage class handles the use of yml files to save instance of mails.
 *
 * @class LocalStorage.
 */
class LocalStorage {

  /**
   * Database table name.
   *
   * @var string Database table name
   */
  private string $localStorageName = 'mail_box_local_storage';

  /**
   * Initialize local storage instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Drupal db connection.
   */
  public function __construct(private readonly Connection $database) {}

  /**
   * Get all saved mails.
   *
   * @return array
   *   Returns mails list.
   *
   * @throws \Exception
   */
  public function getMails(): array {
    $content = $this->database->select($this->localStorageName, 'm');
    $content->addField('m', 'message_no');
    $content->addField('m', 'mailbox_namespace');
    $content->addField('m', 'content_yml');
    $result_st = $content->execute();
    return $result_st->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Get mail saved.
   *
   * @param string $namespace
   *   Namespace name of mail.
   * @param int $message_number
   *   Mail number.
   * @param int $owner_id
   *   Owner uid.
   *
   * @return array|bool|object
   *   Returns array object of mail.
   *
   * @throws \Exception
   */
  public function getMail(string $namespace, int $message_number, int $owner_id): array|bool|object {
    $content = $this->database->select($this->localStorageName, 'm');
    $content->condition('m.message_no', $message_number);
    $content->condition('m.mailbox_namespace', $namespace);
    $content->condition('m.owner_id', $owner_id);
    $content->addField('m', 'message_no');
    $content->addField('m', 'mailbox_namespace');
    $content->addField('m', 'content_yml');
    $result_st = $content->execute();
    return $result_st->fetch(\PDO::FETCH_ASSOC);
  }

  /**
   * Save new mail to local storage.
   *
   * @param string $namespace
   *   New mail namespace.
   * @param int $message_number
   *   New mail number.
   * @param array $data
   *   Mail data to save.
   * @param int $owner_id
   *   Owner uid.
   *
   * @return bool|int|string
   *   True if mail data was saved.
   *
   * @throws \Exception
   */
  public function setMail(string $namespace, int $message_number, array $data, int $owner_id): bool|int|string {
    $config_helper = mail_box_management_service('mail_box_management.config');
    $cache_mail_data = $config_helper->get('cache_mail_data')?->get('cache_mail_data');
    if (empty($cache_mail_data)) {
      return FALSE;
    }
    $query = $this->database->insert($this->localStorageName);
    $query->fields([
      'mailbox_namespace' => $namespace,
      'message_no' => $message_number,
      'owner_id' => $owner_id,
      'content_yml' => $this->writeYaml(Yaml::dump($data)),
    ]);
    EntitySaver::create($data, $owner_id)->save();
    return $query->execute();
  }

  /**
   * Write yaml dump data to file.
   *
   * @param string $data
   *   Data in yml format.
   *
   * @return string
   *   File path of created file.
   */
  private function writeYaml(string $data): string {
    $directory = "public://mailbox_management/local_storage";

    // Get the file system service using your custom service retrieval function.
    $file_system = mail_box_management_service('file_system');

    try {
      // Prepare the directory, creating it if necessary.
      if ($file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        $storage_path = $directory . '/' . time() . '.mailbox.yml';

        // Write data to the specified file.
        $file_system->saveData($data, $storage_path);

        // Return the storage path if successful.
        return $storage_path;
      }
    }
    catch (\Exception) {
    }

    // Return an empty string if the operation fails.
    return '';
  }

  /**
   * Clear all stored mails.
   *
   * @return bool
   *   Returns true if clear was success.
   *
   * @throws \Exception
   */
  public function clearLocalStorage(): bool {
    $query = $this->database->truncate($this->localStorageName);
    $query->execute();
    $query = $this->database->select($this->localStorageName, 'm');
    $query->addField('m', 'message_no');
    $result_st = $query->execute();
    $data = $result_st->fetchAll(\PDO::FETCH_ASSOC);
    $flags = [];
    if (empty($data)) {
      $directory = "public://mailbox_management/local_storage";
      $directory1 = "public://mailbox_management/local_templates";
      $files = array_diff(!empty(scandir($directory)) ? scandir($directory) : [], ['..', '.']);
      $files1 = array_diff(!empty(scandir($directory1)) ? scandir($directory1) : [], ['..', '.']);
      if (!empty($files)) {
        foreach ($files as $file) {
          $flags[] = @unlink("$directory/$file");
        }
      }
      if (!empty($files1)) {
        foreach ($files1 as $file) {
          $flags[] = @unlink("$directory1/$file");
        }
      }
    }
    return in_array(TRUE, $flags);
  }

  /**
   * Saving html template of per build email.
   *
   * @param string $identifier
   *   Mail identifier ie namespace + ._. + mail number.
   * @param string $content
   *   Html content to save.
   *
   * @return bool|string
   *   content is returned.
   */
  public function templateContentSaver(string $identifier, string $content): bool|string {

    $config_helper = mail_box_management_service('mail_box_management.config');
    $cache_mail_content = $config_helper->get('cache_mail_content')?->get('cache_mail_content');
    if (empty($cache_mail_content)) {
      return $content;
    }

    $directory = "public://mailbox_management/local_templates";

    // Get the file system service using your custom service retrieval function.
    $file_system = mail_box_management_service('file_system');

    try {
      // Prepare the directory, creating it if necessary.
      if ($file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        $storage_path = $directory . '/' . md5($identifier) . '.mailbox.yml';

        // Write data to the specified file.
        $file_system->saveData(Yaml::dump(['data' => $content]), $storage_path);
      }
    }
    catch (\Exception) {
    }
    return $content;
  }

  /**
   * Get html content of mail if saved.
   *
   * @param string $identifier
   *   Mail identifier ie namespace+ ._. + mail number.
   *
   * @return string|null
   *   String is return if data found or null if not found.
   */
  public function getTemplateData(string $identifier): string|null {
    $identifier = md5($identifier);
    $directory = "public://mailbox_management/local_templates/$identifier.mailbox.yml";
    if (file_exists($directory)) {
      $content = Yaml::parseFile($directory);
      return $content['data'] ?? NULL;
    }
    return NULL;
  }

}
