<?php

namespace Drupal\gitlab_api;

/**
 * A commit action object for \Drupal\gitlab_api\Api::createCommit.
 */
class CommitAction {

  public const TYPE_CREATE = 'create';
  public const TYPE_DELETE = 'delete';
  public const TYPE_MOVE = 'move';
  public const TYPE_UPDATE = 'update';

  public const ENCODING_TEXT = 'text';
  public const ENCODING_BASE64 = 'base64';

  /**
   * The type of commit, which can be one of the TYPE_* constants.
   *
   * @var string
   */
  protected string $type;

  /**
   * The relative path to the file that should be committed.
   *
   * @var string
   */
  protected string $path;

  /**
   * The content of that file to be committed.
   *
   * @var string
   */
  protected string $content;

  /**
   * The encoding of content, which can be one of the ENCODING_* constants.
   *
   * @var string
   */
  protected string $encoding;

  /**
   * The previous path, if the commit is about moving a file.
   *
   * @var string|null
   */
  protected ?string $previousPath;

  /**
   * Creates and return a new CommitAction object.
   *
   * @param string $type
   *   The type of commit, which can be one of the TYPE_* constants.
   * @param string $path
   *   The relative path to the file that should be committed.
   * @param string $content
   *   The content of that file to be committed.
   * @param string $encoding
   *   The encoding of content, which can be one of the ENCODING_* constants.
   * @param string $previousPath
   *   The previous path, if the commit is about moving a file.
   *
   * @return \Drupal\gitlab_api\CommitAction
   *   The new CommitAction object.
   *
   * @see \Gitlab\Api\Repositories::createCommit
   */
  public static function create(string $type, string $path, string $content, string $encoding = self::ENCODING_TEXT, string $previousPath = ''): CommitAction {
    assert(in_array($type, [
      self::TYPE_CREATE,
      self::TYPE_DELETE,
      self::TYPE_MOVE,
      self::TYPE_UPDATE,
    ]));
    assert(in_array($encoding, [
      self::ENCODING_TEXT,
      self::ENCODING_BASE64,
    ]));
    if ($type === self::TYPE_MOVE) {
      assert($previousPath !== '');
    }
    $instance = new self();
    $instance->type = $type;
    $instance->path = $path;
    $instance->content = $content;
    $instance->encoding = $encoding;
    $instance->previousPath = $previousPath;
    return $instance;
  }

  /**
   * Get an array of all relevant properties.
   *
   * @return array
   *   The array of relevant properties.
   */
  public function toArray(): array {
    return [
      'action' => $this->type,
      'file_path' => $this->path,
      'content' => $this->content,
      'encoding' => $this->encoding,
      'previous_path' => $this->previousPath,
    ];
  }

}
